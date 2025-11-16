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

@app.route('/api/rpos-login', methods=['POST'])
def create_rpos_login():
    """Create a new RPOS_LOGIN record"""
    conn = get_db_connection()
    if not conn:
        return jsonify({'success': False, 'message': 'Database connection failed'}), 500
    
    try:
        data = request.get_json()
        
        # Validate required fields
        required_fields = ['device_id', 'employee_id', 'lan_ip']
        for field in required_fields:
            if not data.get(field):
                return jsonify({
                    'success': False,
                    'message': f'Missing required field: {field}'
                }), 400
        
        cursor = conn.cursor()
        
        # Try to get next sequence value for ID (common Oracle pattern)
        # If sequence doesn't exist, the trigger or default will handle it
        inserted_id = None
        try:
            cursor.execute("SELECT RPOS_LOGIN_SEQ.NEXTVAL FROM DUAL")
            inserted_id = cursor.fetchone()[0]
        except:
            # Sequence might not exist or ID might be auto-generated via trigger
            # Try alternative sequence names
            try:
                cursor.execute("SELECT SEQ_RPOS_LOGIN.NEXTVAL FROM DUAL")
                inserted_id = cursor.fetchone()[0]
            except:
                # No sequence found, will rely on trigger or default
                pass
        
        # Insert into RPOS_LOGIN table
        # CREATED_AT and UPDATED_AT use SYSDATE (default)
        if inserted_id:
            cursor.execute("""
                INSERT INTO RPOS_LOGIN (
                    ID,
                    DEVICE_ID,
                    EMPLOYEE_ID,
                    ADMIN_EMPLOYEE_ID,
                    APPROVAL_FLAG,
                    LAN_IP,
                    CREATED_AT,
                    UPDATED_AT
                ) VALUES (
                    :id,
                    :device_id,
                    :employee_id,
                    :admin_employee_id,
                    :approval_flag,
                    :lan_ip,
                    SYSDATE,
                    SYSDATE
                )
            """, {
                'id': inserted_id,
                'device_id': str(data['device_id'])[:128],  # VARCHAR2(128)
                'employee_id': str(data['employee_id'])[:32],  # VARCHAR2(32)
                'admin_employee_id': str(data.get('admin_employee_id', data['employee_id']))[:32],  # VARCHAR2(32)
                'approval_flag': data.get('approval_flag', 'N'),  # CHAR(1), default 'N'
                'lan_ip': str(data['lan_ip'])[:64]  # VARCHAR2(64)
            })
        else:
            # If no sequence, let trigger or default handle ID
            cursor.execute("""
                INSERT INTO RPOS_LOGIN (
                    DEVICE_ID,
                    EMPLOYEE_ID,
                    ADMIN_EMPLOYEE_ID,
                    APPROVAL_FLAG,
                    LAN_IP,
                    CREATED_AT,
                    UPDATED_AT
                ) VALUES (
                    :device_id,
                    :employee_id,
                    :admin_employee_id,
                    :approval_flag,
                    :lan_ip,
                    SYSDATE,
                    SYSDATE
                )
            """, {
                'device_id': str(data['device_id'])[:128],  # VARCHAR2(128)
                'employee_id': str(data['employee_id'])[:32],  # VARCHAR2(32)
                'admin_employee_id': str(data.get('admin_employee_id', data['employee_id']))[:32],  # VARCHAR2(32)
                'approval_flag': data.get('approval_flag', 'N'),  # CHAR(1), default 'N'
                'lan_ip': str(data['lan_ip'])[:64]  # VARCHAR2(64)
            })
            
            # Try to get the inserted ID after insert
            try:
                cursor.execute("SELECT RPOS_LOGIN_SEQ.CURRVAL FROM DUAL")
                inserted_id = cursor.fetchone()[0]
            except:
                pass
        
        conn.commit()
        
        return jsonify({
            'success': True,
            'message': 'Registration stored successfully',
            'id': inserted_id,
            'device_id': data['device_id'],
            'employee_id': data['employee_id']
        }), 201
        
    except cx_Oracle.Error as error:
        conn.rollback()
        print(f"Oracle error: {error}")
        return jsonify({'success': False, 'message': f'Oracle error: {str(error)}'}), 500
    except Exception as error:
        conn.rollback()
        print(f"General error: {error}")
        return jsonify({'success': False, 'message': str(error)}), 500
    finally:
        if 'cursor' in locals():
            cursor.close()
        if conn:
            conn.close()

@app.route('/api/rpos-login/status', methods=['GET'])
def get_rpos_login_status():
    """Get approval status from RPOS_LOGIN table"""
    conn = get_db_connection()
    if not conn:
        return jsonify({'success': False, 'message': 'Database connection failed'}), 500
    
    try:
        device_id = request.args.get('device_id')
        employee_id = request.args.get('employee_id')
        
        if not device_id:
            return jsonify({'success': False, 'message': 'Missing device_id parameter'}), 400
        
        cursor = conn.cursor()
        
        # Query RPOS_LOGIN table
        if employee_id:
            cursor.execute("""
                SELECT ID, DEVICE_ID, EMPLOYEE_ID, ADMIN_EMPLOYEE_ID, APPROVAL_FLAG, LAN_IP, CREATED_AT, UPDATED_AT
                FROM RPOS_LOGIN 
                WHERE DEVICE_ID = :device_id AND EMPLOYEE_ID = :employee_id
                ORDER BY CREATED_AT DESC
            """, {
                'device_id': str(device_id),
                'employee_id': str(employee_id)
            })
        else:
            cursor.execute("""
                SELECT ID, DEVICE_ID, EMPLOYEE_ID, ADMIN_EMPLOYEE_ID, APPROVAL_FLAG, LAN_IP, CREATED_AT, UPDATED_AT
                FROM RPOS_LOGIN 
                WHERE DEVICE_ID = :device_id
                ORDER BY CREATED_AT DESC
            """, {
                'device_id': str(device_id)
            })
        
        row = cursor.fetchone()
        if row:
            return jsonify({
                'success': True,
                'found': True,
                'id': int(row[0]) if row[0] is not None else None,
                'device_id': row[1] if row[1] is not None else '',
                'employee_id': row[2] if row[2] is not None else '',
                'admin_employee_id': row[3] if row[3] is not None else '',
                'approval_flag': row[4] if row[4] is not None else 'N',
                'lan_ip': row[5] if row[5] is not None else '',
                'created_at': str(row[6]) if row[6] is not None else '',
                'updated_at': str(row[7]) if row[7] is not None else '',
            })
        else:
            return jsonify({
                'success': False,
                'found': False,
                'message': 'Registration not found'
            }), 404
            
    except cx_Oracle.Error as error:
        print(f"Oracle error: {error}")
        return jsonify({'success': False, 'message': f'Oracle error: {str(error)}'}), 500
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

