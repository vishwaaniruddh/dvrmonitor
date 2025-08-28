# DVR Node Dashboard

A high-performance Node.js backend for DVR status monitoring, using Fastify, MySQL2, axios, and worker_threads for true parallelism.

## Features
- Fetches DVRs from MySQL
- Pings each DVR before making API calls
- Uses worker threads for concurrent DVR status checks (rolling pool)
- REST API endpoint: `/api/batch?pool=50` (pool size adjustable)

## Usage
1. Configure your MySQL connection in `server.js`.
2. Start the server:
   ```
   node server.js
   ```
3. Access the batch endpoint:
   ```
   http://localhost:3000/api/batch?pool=50
   ```

## Customization
- Expand `worker.js` to fetch more DVR details as needed (camera status, storage, etc).
- Adjust pool size for optimal performance.
