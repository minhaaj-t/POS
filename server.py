from flask import Flask, request, jsonify
import cx_Oracle
from flask_cors import CORS
import os

app = Flask(__name__)
CORS(app)  # Allow Laravel to call this API

# Database configuration
DB_CONFIG = {
    'user': 'rfim',
    'password': 'rfim',
    'dsn': '192.168.1.225:1521/rgc'
}

def get_db_connection():
    """Create and return Oracle database connection"""
    try:
        connection = cx_Oracle.connect(
            user=DB_CONFIG['user'],
            password=DB_CONFIG['password'],
            dsn=DB_CONFIG['dsn']
        )
        return connection
    except cx_Oracle.Error as error:
        print(f"Error connecting to Oracle: {error}")
        return None

@app.route('/api/employees/<employee_id>', methods=['GET'])
def get_employee(employee_id):
    """Get employee by ID from Oracle"""
    conn = get_db_connection()
    if not conn:
        return jsonify({'success': False, 'message': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor()
        # Query APPLICATIONUSER table
        # EMPLOYEECODE (NUMBER) - Employee ID
        # USERID (VARCHAR2) - Employee Name
        # PASSWORD (VARCHAR2) - Password
        cursor.execute("""
            SELECT EMPLOYEECODE, USERID, PASSWORD, LOCATIONCODE 
            FROM APPLICATIONUSER 
            WHERE EMPLOYEECODE = :id
        """, {'id': int(employee_id)})
        
        row = cursor.fetchone()
        if row:
            employee_code = str(row[0]) if row[0] is not None else ''
            user_id = row[1] if row[1] is not None else ''
            password = row[2] if row[2] is not None else ''
            location_code = int(row[3]) if row[3] is not None else None
            
            return jsonify({
                'success': True,
                'employee': {
                    'id': employee_code,
                    'name': user_id
                },
                'username': employee_code,  # Username = EMPLOYEECODE
                'password': password,  # Password from database
                'location_code': location_code  # LOCATIONCODE for fetching shop details
            })
        else:
            return jsonify({'success': False, 'message': 'Employee not found'}), 404
            
    except cx_Oracle.Error as error:
        print(f"Oracle error: {error}")
        return jsonify({'success': False, 'message': str(error)}), 500
    except Exception as error:
        print(f"General error: {error}")
        return jsonify({'success': False, 'message': str(error)}), 500
    finally:
        if 'cursor' in locals():
            cursor.close()
        if conn:
            conn.close()

@app.route('/health', methods=['GET'])
def health():
    """Health check endpoint"""
    return jsonify({'status': 'ok', 'service': 'oracle-connector'})

@app.route('/api/locations/<location_code>', methods=['GET'])
def get_location(location_code):
    """Get location details by LOCATIONCODE from LOCATIONMASTER"""
    conn = get_db_connection()
    if not conn:
        return jsonify({'success': False, 'message': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor()
        # Query LOCATIONMASTER table
        cursor.execute("""
            SELECT LOCATIONCODE, LOCATIONNAME, MANAGER, ADDRESS, TELEPHONE, FAX, EMAILID
            FROM LOCATIONMASTER 
            WHERE LOCATIONCODE = :code
        """, {'code': int(location_code)})
        
        row = cursor.fetchone()
        if row:
            return jsonify({
                'success': True,
                'location': {
                    'location_code': int(row[0]) if row[0] is not None else None,
                    'location_name': row[1] if row[1] is not None else '',
                    'manager': row[2] if row[2] is not None else '',
                    'address': row[3] if row[3] is not None else '',
                    'telephone': row[4] if row[4] is not None else '',
                    'fax': row[5] if row[5] is not None else '',
                    'emailid': row[6] if row[6] is not None else ''
                }
            })
        else:
            return jsonify({'success': False, 'message': 'Location not found'}), 404
            
    except cx_Oracle.Error as error:
        print(f"Oracle error: {error}")
        return jsonify({'success': False, 'message': str(error)}), 500
    except Exception as error:
        print(f"General error: {error}")
        return jsonify({'success': False, 'message': str(error)}), 500
    finally:
        if 'cursor' in locals():
            cursor.close()
        if conn:
            conn.close()

@app.route('/test-connection', methods=['GET'])
def test_connection():
    """Test Oracle database connection"""
    conn = get_db_connection()
    if conn:
        conn.close()
        return jsonify({'success': True, 'message': 'Connection successful'})
    else:
        return jsonify({'success': False, 'message': 'Connection failed'}), 500

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port, debug=True)

