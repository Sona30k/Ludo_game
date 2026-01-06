# WebSocket Server Setup Guide

## Quick Start

### 1. Install Dependencies
```bash
cd websocket
npm install
```

### 2. Configure Environment
Create a `.env` file in the `websocket` folder with the following content:

```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=ludo_platform
PORT=3000
NODE_ENV=development
CORS_ORIGIN=http://localhost
```

**Important:** Update the values according to your database configuration.

### 3. Start the Server

**Development mode (with auto-reload):**
```bash
npm run dev
```

**Production mode:**
```bash
npm start
```

### 4. Verify Server is Running
You should see:
```
✅ Database connected successfully
🚀 WebSocket server running on port 3000
📡 Socket.IO ready for connections
```

## Database Requirements

Make sure your database has the following tables:
- `users` - User accounts
- `tables` - Game tables  
- `games` - Game sessions
- `wallets` - User wallets
- `wallet_transactions` - Transaction history
- `dice_override` - Admin dice control (with columns: table_id, dice_value, set_by, timestamp, used)

## Testing Connection

You can test the WebSocket connection using a simple HTML file or browser console:

```javascript
const socket = io('http://localhost:3000', {
    auth: {
        userId: 1,  // Your user ID from database
        username: 'testuser'  // Your username
    }
});

socket.on('connect', () => {
    console.log('Connected to WebSocket server!');
});
```

## Troubleshooting

### Database Connection Error
- Check your `.env` file has correct database credentials
- Ensure MySQL/MariaDB is running
- Verify database name `ludo_platform` exists

### Port Already in Use
- Change `PORT` in `.env` to a different port (e.g., 3001)
- Or stop the process using port 3000

### CORS Errors
- Update `CORS_ORIGIN` in `.env` to match your frontend URL
- For development, you can use `http://localhost` or `*` (not recommended for production)

## Next Steps

Once the WebSocket server is running:
1. Integrate Socket.IO client in your frontend
2. Create the game UI page
3. Connect with PHP backend APIs

