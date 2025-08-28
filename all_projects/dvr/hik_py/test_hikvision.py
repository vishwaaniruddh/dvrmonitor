import sys
import json
from datetime import datetime
import urllib3
import requests
from hikvisionapi import Client

# Disable SSL warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

def get_hikvision_data(ip, port, username, password):
    try:
        # Create a client instance with increased timeout
        cam = Client(f'http://{ip}:{port}', username, password, timeout=5)
        
        # Basic connectivity test
        try:
            status = cam.System.status(method='get', present='text')
            if '<status>' not in status:
                raise Exception("Invalid status response")
        except Exception as e:
            print(f"Error getting system status: {str(e)}", file=sys.stderr)
            raise e

        # Try to get basic device info
        device_time = ''
        try:
            # Try direct HTTP request to a basic endpoint
            url = f'http://{ip}:{port}/ISAPI/System/time'
            response = requests.get(url, 
                auth=requests.auth.HTTPDigestAuth(username, password),
                verify=False,
                timeout=5
            )
            if response.status_code == 200 and '<localTime>' in response.text:
                import re
                match = re.search(r'<localTime>(.*?)</localTime>', response.text)
                if match:
                    device_time = match.group(1)
        except Exception as e:
            print(f"Error getting time info: {str(e)}", file=sys.stderr)

        # Format the data with basic online status
        formatted_data = {
            'status': 'ONLINE',
            'deviceInfo': {
                'dvrTime': device_time,
                'loginTime': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'currentDateTime': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            },
            'cameraInfo': {
                'totalCameras': 0,
                'cameraStatus': []
            },
            'storageInfo': {
                'storageType': 'N/A',
                'storageStatus': 'N/A',
                'storageCapacity': 'N/A',
                'storageFree': 'N/A'
            },
            'recordingInfo': {
                'recordingFrom': '',
                'recordingTo': ''
            }
        }

        # Try to get basic camera count
        try:
            url = f'http://{ip}:{port}/ISAPI/System/Video/inputs/channels'
            response = requests.get(url, 
                auth=requests.auth.HTTPDigestAuth(username, password),
                verify=False,
                timeout=5
            )
            if response.status_code == 200:
                # Count camera tags in response
                camera_count = response.text.count('<VideoInputChannel>')
                formatted_data['cameraInfo']['totalCameras'] = camera_count
                
                # Try to parse camera status
                import re
                cameras = []
                matches = re.finditer(r'<id>(.*?)</id>.*?<enabled>(.*?)</enabled>', response.text, re.DOTALL)
                for match in matches:
                    camera_id = match.group(1)
                    enabled = match.group(2).lower() == 'true'
                    cameras.append({
                        'number': camera_id,
                        'status': 'Working' if enabled else 'Not Working'
                    })
                formatted_data['cameraInfo']['cameraStatus'] = cameras
        except Exception as e:
            print(f"Error getting camera info: {str(e)}", file=sys.stderr)

        return formatted_data
        
    except Exception as e:
        print(f"Error occurred: {str(e)}", file=sys.stderr)
        return {
            'status': 'ERROR',
            'error': str(e),
            'deviceInfo': {
                'dvrTime': '',
                'loginTime': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'currentDateTime': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            },
            'cameraInfo': {
                'totalCameras': 0,
                'cameraStatus': []
            },
            'storageInfo': {
                'storageType': 'N/A',
                'storageStatus': 'N/A',
                'storageCapacity': 'N/A',
                'storageFree': 'N/A'
            },
            'recordingInfo': {
                'recordingFrom': '',
                'recordingTo': ''
            }
        }

if __name__ == "__main__":
    if len(sys.argv) != 5:
        print(json.dumps({
            'status': 'ERROR',
            'error': 'Invalid arguments. Usage: script.py <ip> <port> <username> <password>'
        }))
        sys.exit(1)
        
    ip = sys.argv[1]
    port = sys.argv[2]
    username = sys.argv[3]
    password = sys.argv[4]
    
    result = get_hikvision_data(ip, port, username, password)
    print(json.dumps(result)) 