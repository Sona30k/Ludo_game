const mysql = require('mysql2/promise');
const pool = mysql.createPool({ host: 'localhost', user: 'admin', password: 'P@sswORd123', database: 'ludo' });
module.exports = pool;
