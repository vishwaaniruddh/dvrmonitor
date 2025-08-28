const { workerData, parentPort } = require('worker_threads');
const axios = require('axios');
const { execSync } = require('child_process');

async function ping(ip) {
  try {
    // Windows ping: -n 1 -w 1000
    execSync(`ping -n 1 -w 1000 ${ip}`, { stdio: 'ignore' });
    return true;
  } catch {
    return false;
  }
}

async function getDvrStatus(dvr) {
  if (!(await ping(dvr.ip))) {
    parentPort.postMessage({ ip: dvr.ip, status: 'NO NETWORK' });
    return;
  }
  try {
    const url = `http://${dvr.ip}:${dvr.port}/cgi-bin/global.cgi?action=getCurrentTime`;
    const res = await axios.get(url, {
      auth: { username: dvr.username, password: dvr.password },
      timeout: 3000,
      validateStatus: () => true
    });
    if (res.status === 200 && res.data.includes('result=')) {
      parentPort.postMessage({ ip: dvr.ip, status: 'OK', dvrTime: res.data.match(/result=(.*)/)?.[1]?.trim() });
    } else {
      parentPort.postMessage({ ip: dvr.ip, status: 'FAIL', error: res.status });
    }
  } catch (e) {
    parentPort.postMessage({ ip: dvr.ip, status: 'FAIL', error: e.message });
  }
}

getDvrStatus(workerData);
