const Fastify = require('fastify');
const mysql = require('mysql2/promise');
const { Worker } = require('worker_threads');
const path = require('path');

const fastify = Fastify({ logger: true });

// MySQL config (edit as needed)
const dbConfig = {
  host: 'localhost',
  user: 'reporting',
  password: 'reporting',
  database: 'esurv',
};

// Fetch DVRs from MySQL
async function fetchDVRs() {
  const conn = await mysql.createConnection(dbConfig);
  const [rows] = await conn.execute(
    "SELECT IPAddress as ip, port, UserName as username, Password as password FROM all_dvr_live WHERE dvrname in ('CPPLUS','CPPLUS_ORANGE') and LOWER(live)='y' LIMIT 20"
  );
  await conn.end();
  return rows;
}

// Run a worker for each DVR (with a pool limit)
function runDVRWorker(dvr) {
  return new Promise((resolve) => {
    const worker = new Worker(path.join(__dirname, 'worker.js'), { workerData: dvr });
    worker.on('message', resolve);
    worker.on('error', err => resolve({ ip: dvr.ip, status: 'ERROR', error: err.message }));
    worker.on('exit', code => { if (code !== 0) resolve({ ip: dvr.ip, status: 'ERROR', error: 'Worker exited' }); });
  });
}

async function processDVRs(poolSize = 50) {
  const dvrs = await fetchDVRs();
  const results = [];
  let idx = 0;
  async function next() {
    if (idx >= dvrs.length) return;
    const dvr = dvrs[idx++];
    const result = await runDVRWorker(dvr);
    results.push(result);
    return next();
  }
  // Start poolSize workers
  const pool = Array.from({ length: Math.min(poolSize, dvrs.length) }, next);
  await Promise.all(pool);
  return results;
}

fastify.get('/api/batch', async (req, reply) => {
  const poolSize = parseInt(req.query.pool) || 50;
  const results = await processDVRs(poolSize);
  return { total: results.length, results };
});

fastify.listen({ port: 3000, host: '0.0.0.0' }, err => {
  if (err) throw err;
  console.log('Server running on http://localhost:3000');
});
