module.exports = {
  apps: [{
    name: 'ludo-websocket',
    script: './server.js',
    env: {
      DB_HOST: 'localhost',
      DB_USER: 'admin',
      DB_PASSWORD: 'P@sswORd123',
      DB_NAME: 'ludo',
      PORT: 3000,
      NODE_ENV: 'production',
      CORS_ORIGIN: 'https://91.108.104.181'
    }
  }]
};
