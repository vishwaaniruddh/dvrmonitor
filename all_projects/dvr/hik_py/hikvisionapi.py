import inspect
import json
from urllib.parse import urljoin
import requests
from requests.auth import HTTPBasicAuth, HTTPDigestAuth
import xmltodict
import sys

class DynamicMethod(object):
    def __init__(self, client, path):
        self.client = client
        self.path = path

    def __repr__(self):
        return f"<DynamicMethod client={self.client} path={self.path}"

    def __getattr__(self, key):
        return DynamicMethod(self.client, '/'.join((self.path, key)))

    def __getitem__(self, item):
        return DynamicMethod(self.client, self.path + "/" + str(item))

    def __call__(self, **kwargs):
        assert 'method' in kwargs, "set http method in args"
        return self.client.request(self.path, **kwargs)

def response_parser(response, present='dict'):
    """ Convert Hikvision results """
    if isinstance(response, (list,)):
        result = "".join(response)
    elif isinstance(response, str):
        result = response
    else:
        result = response.text

    if present is None or present == 'dict':
        if isinstance(response, (list,)):
            events = []
            for event in response:
                e = json.loads(json.dumps(xmltodict.parse(event)))
                events.append(e)
            return events
        return json.loads(json.dumps(xmltodict.parse(result)))
    else:
        return result

class Client:
    def __init__(self, host, login=None, password=None, timeout=3, isapi_prefix='ISAPI'):
        self.host = host
        self.login = login
        self.password = password
        self.timeout = float(timeout)
        self.isapi_prefix = isapi_prefix
        self.req = self._check_session()
        self.count_events = 1

    def _check_session(self):
        full_url = urljoin(self.host, self.isapi_prefix + '/System/status')
        session = requests.session()
        
        # Try different authentication methods
        auth_methods = [
            ('digest', HTTPDigestAuth(self.login, self.password)),
            ('basic', HTTPBasicAuth(self.login, self.password)),
            # Some Hikvision devices use basic auth with a special header
            ('basic_with_header', HTTPBasicAuth(self.login, self.password))
        ]
        
        last_error = None
        for auth_type, auth in auth_methods:
            try:
                session.auth = auth
                headers = {}
                if auth_type == 'basic_with_header':
                    headers['Authorization'] = f'Basic {self.login}:{self.password}'
                
                print(f"Trying {auth_type} authentication...", file=sys.stderr)
                response = session.get(full_url, timeout=self.timeout, verify=False, headers=headers)
                
                if response.status_code == 200:
                    print(f"Successfully connected using {auth_type} authentication", file=sys.stderr)
                    return session
                
                last_error = f"HTTP {response.status_code}: {response.text}"
            except requests.exceptions.RequestException as e:
                last_error = str(e)
                continue
        
        raise Exception(f"All authentication methods failed. Last error: {last_error}")

    def __getattr__(self, key):
        return DynamicMethod(self, key)

    def stream_request(self, method, full_url, **data):
        events = []
        response = self.req.request(method, full_url, timeout=self.timeout, stream=True, verify=False, **data)
        for chunk in response.iter_lines(chunk_size=1024, delimiter=b'--boundary'):
            if chunk:
                xml = chunk.split(b'\r\n\r\n')[1].decode("utf-8")
                events.append(xml)
                if len(events) == self.count_events:
                    return events

    def opaque_request(self, method, full_url, **data):
        return self.req.request(method, full_url, timeout=self.timeout, stream=True, verify=False, **data)

    def common_request(self, method, full_url, **data):
        response = self.req.request(method, full_url, timeout=self.timeout, verify=False, **data)
        response.raise_for_status()
        return response

    def request(self, *args, **kwargs):
        url_path = list(args)
        url_path.insert(0, self.isapi_prefix)
        full_url = urljoin(self.host, "/".join(url_path))
        method = kwargs['method']

        data = kwargs.copy()
        data.pop('present', None)
        data.pop('method')
        supported_types = {
            'stream': self.stream_request,
            'opaque_data': self.opaque_request
        }
        return_type = data.pop('type', '').lower()

        if return_type in supported_types and method == 'get':
            return supported_types[return_type](method, full_url, **data)
        else:
            return self.common_request(method, full_url, **data) 