# Python Oracle Server Setup

This Python server acts as a bridge between Laravel and Oracle database.

## Prerequisites

1. **Python 3.7+** installed
2. **Oracle Instant Client** installed and configured
3. **cx_Oracle** Python package (installed via requirements.txt)

## Installation

### 1. Install Python Dependencies

```bash
pip install -r requirements.txt
```

### 2. Install Oracle Instant Client

**Windows:**
- Download Oracle Instant Client from Oracle website
- Extract to a folder (e.g., `C:\oracle\instantclient_21_8`)
- Add the folder to your system PATH

**Linux:**
```bash
# Download and extract Oracle Instant Client
# Add to LD_LIBRARY_PATH
export LD_LIBRARY_PATH=/path/to/instantclient:$LD_LIBRARY_PATH
```

**macOS:**
```bash
# Download and extract Oracle Instant Client
# Add to DYLD_LIBRARY_PATH
export DYLD_LIBRARY_PATH=/path/to/instantclient:$DYLD_LIBRARY_PATH
```

## Configuration

Edit `server.py` to update database connection:

```python
DB_CONFIG = {
    'user': 'rfim',
    'password': 'rfim',
    'dsn': '192.168.1.225:1521/rgc'
}
```

## Running the Server

### Development Mode

```bash
python server.py
```

The server will start on `http://localhost:5000`

### Production Mode (using Gunicorn)

```bash
pip install gunicorn
gunicorn -w 4 -b 0.0.0.0:5000 server:app
```

## API Endpoints

### Get Employee by ID
```
GET /api/employees/{employee_id}
```

**Response:**
```json
{
    "success": true,
    "employee": {
        "id": "7469",
        "name": "Muhammed Minhaj Mahroof"
    },
    "username": "7469"
}
```

### Health Check
```
GET /health
```

### Test Connection
```
GET /test-connection
```

## Laravel Integration

1. Add to your `.env` file:
```
PYTHON_SERVER_URL=http://localhost:5000
```

2. The Laravel controller will automatically:
   - Try to fetch from Python server first
   - Fall back to hardcoded data if server is unavailable

## Troubleshooting

### Connection Issues

1. **Check Oracle server is accessible:**
   ```bash
   telnet 192.168.1.225 1521
   ```

2. **Test Oracle connection:**
   ```bash
   curl http://localhost:5000/test-connection
   ```

3. **Check Python server logs** for detailed error messages

### Common Errors

- **"ORA-12154: TNS:could not resolve the connect identifier"**
  - Check DSN format: `host:port/service_name`
  - Verify Oracle server is reachable

- **"ModuleNotFoundError: No module named 'cx_Oracle'"**
  - Run: `pip install cx_Oracle`

- **"libclntsh.so: cannot open shared object file"**
  - Oracle Instant Client not in library path
  - Add to LD_LIBRARY_PATH (Linux) or DYLD_LIBRARY_PATH (macOS)

## Notes

- The server uses Flask with CORS enabled to allow Laravel requests
- Default port is 5000 (configurable via PORT environment variable)
- The Oracle query in `server.py` may need adjustment based on your actual database schema

