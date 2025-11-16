from datetime import date, datetime
import decimal
import os

import cx_Oracle
from flask import Flask, jsonify, request
from flask_cors import CORS

app = Flask(__name__)
CORS(app)  # Enable CORS for Flutter app

# Database configuration
DB_CONFIG = {
    'user': 'rfim',
    'password': 'rfim',
    'dsn': '192.168.1.225:1521/rgc'
}

RPOS_LOGIN_TABLE = 'RPOS_LOGIN'

ITEMMASTER_ALLOWED_COLUMNS = {
    'ITEMCODE',
    'ITEMNAME',
    'ITEMNAMEARA',
    'ARABICNAME',
    'BARCODE',
    'UNIT',
    'BASEUOM',
    'RETAILPRICE',
    'WHOLESALEPRICE',
    'BRANCHPRICE',
    'COSTPRICE',
    'CURRENTSTOCK',
    'CATEGORYCODE',
    'CATEGORYNAME',
    'MAINCATEGORY',
    'MAINCATEGORYCODE',
    'MAINCATEGORYNAME',
    'MICROCATEGORYCODE',
    'MICROCATEGORYNAME',
    'BRANDCODE',
    'BRANDNAME',
    'DESCRIPTION',
    'STORE1',
    'STORE2',
    'STORE3',
    'STORE4',
    'STORE5',
    'STORE6',
    'BRANCHSTOCK',
    'LOCATION',
    'LOCATIONCODE',
    'PROPERTY',
    'PROPERTYNAME',
    'SUPPLIERCODE',
    'SUPPLIERNAME',
    'SUPPLIERTYPE',
    'ONLINEPRICE',
    'FACTOR',
    'ITEMFLAG',
    'QUANTITYLIMIT',
    'THIRDPRICE',
    'BRANCHPRICE',
    'AVERAGECOST',
    'LANDINGCOST',
    'WHOLESALEPRICE',
    'ORIGIN',
}

ITEMMASTER_DEFAULT_COLUMNS = [
    'ITEMCODE',
    'ITEMNAME',
    'ITEMNAMEARA',
    'BARCODE',
    'UNIT',
    'RETAILPRICE',
    'WHOLESALEPRICE',
    'BRANCHPRICE',
    'COSTPRICE',
    'CURRENTSTOCK',
    'CATEGORYCODE',
    'CATEGORYNAME',
    'BRANDCODE',
    'BRANDNAME',
    'DESCRIPTION',
]


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
        print(f"Error connecting to Oracle database: {error}")
        raise

@app.route('/', methods=['GET'])
def root():
    """Root endpoint - lists available API endpoints"""
    return jsonify({
        'message': 'RAB Packing API Server',
        'status': 'running',
        'endpoints': {
            'health': '/api/health',
            'user_by_code': '/api/user/<employee_code>',
            'user_search': '/api/user/search (POST)',
            'rpos_login': '/api/rpos-login (POST)',
            'rpos_login_status': '/api/rpos-login/status?device_id=...',
            'location': '/api/location/<location_code>',
            'itemmaster': '/api/itemmaster/details'
        }
    }), 200

@app.route('/api/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({'status': 'ok', 'message': 'Server is running'}), 200

@app.route('/api/user/<int:employee_code>', methods=['GET'])
def get_user_by_employee_code(employee_code):
    """Fetch user details from APPLICATIONUSER table based on Employee Code"""
    connection = None
    cursor = None
    
    try:
        connection = get_db_connection()
        cursor = connection.cursor()
        
        # Query to fetch user details
        query = """
            SELECT 
                COUNTER,
                EMPLOYEECODE,
                LOCATIONCODE,
                PASSWORD,
                USERID
            FROM APPLICATIONUSER
            WHERE EMPLOYEECODE = :employee_code
        """
        
        cursor.execute(query, {'employee_code': employee_code})
        result = cursor.fetchone()
        
        if result:
            # Map database columns to response
            user_data = {
                'employee_id': str(result[1]),  # EMPLOYEECODE
                'username': str(result[1]),     # EMPLOYEECODE (same as employee_id)
                'counter': result[0] if result[0] else 'N',  # COUNTER
                'name': result[4] if result[4] else '',      # USERID
                'password': result[3] if result[3] else '',  # PASSWORD
                'location_code': result[2] if result[2] else None,  # LOCATIONCODE
                'found': True
            }
            return jsonify(user_data), 200
        else:
            return jsonify({
                'found': False,
                'message': f'User with Employee Code {employee_code} not found'
            }), 404
            
    except cx_Oracle.Error as error:
        print(f"Database error: {error}")
        return jsonify({
            'error': 'Database error',
            'message': str(error)
        }), 500
    except Exception as error:
        print(f"Unexpected error: {error}")
        return jsonify({
            'error': 'Internal server error',
            'message': str(error)
        }), 500
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()


def _normalise_flag(flag) -> str:
    if not flag:
        return 'N'
    flag = flag.strip().upper()
    return 'Y' if flag == 'Y' else 'N'


def _serialise_value(value):
    if value is None:
        return None
    if isinstance(value, decimal.Decimal):
        if value == value.to_integral_value():
            return int(value)
        return float(value)
    if isinstance(value, (datetime, date)):
        return value.isoformat()
    if isinstance(value, cx_Oracle.LOB):
        return value.read()
    return value


@app.route('/api/rpos-login', methods=['POST'])
def upsert_rpos_login():
    """Insert or update a record in RPOS_LOGIN."""
    payload = request.get_json(silent=True) or {}

    device_id = (payload.get('device_id') or '').strip()
    employee_id = (payload.get('employee_id') or '').strip()
    admin_employee_id = (payload.get('admin_employee_id') or '').strip()
    approval_flag = _normalise_flag(payload.get('approval_flag'))
    lan_ip = (payload.get('lan_ip') or '').strip()

    if admin_employee_id == '':
        admin_employee_id = employee_id

    if not device_id or not employee_id or not admin_employee_id:
        return jsonify({
            'error': 'Bad request',
            'message': 'device_id, employee_id and admin_employee_id are required'
        }), 400

    connection = None
    cursor = None

    merge_sql = f"""
        MERGE INTO {RPOS_LOGIN_TABLE} tgt
        USING (SELECT :device_id AS device_id FROM dual) src
        ON (tgt.DEVICE_ID = src.device_id)
        WHEN MATCHED THEN
            UPDATE SET
                EMPLOYEE_ID = :employee_id,
                ADMIN_EMPLOYEE_ID = :admin_employee_id,
                APPROVAL_FLAG = :approval_flag,
                LAN_IP = :lan_ip,
                UPDATED_AT = SYSDATE
        WHEN NOT MATCHED THEN
            INSERT (DEVICE_ID, EMPLOYEE_ID, ADMIN_EMPLOYEE_ID, LAN_IP, APPROVAL_FLAG, CREATED_AT, UPDATED_AT)
            VALUES (:device_id, :employee_id, :admin_employee_id, :lan_ip, :approval_flag, SYSDATE, SYSDATE)
    """

    try:
        connection = get_db_connection()
        cursor = connection.cursor()
        cursor.execute(
            merge_sql,
            device_id=device_id,
            employee_id=str(employee_id),
            admin_employee_id=str(admin_employee_id),
            lan_ip=lan_ip,
            approval_flag=approval_flag,
        )
        connection.commit()

        return jsonify({
            'success': True,
            'approval_flag': approval_flag
        }), 200

    except cx_Oracle.Error as error:
        if connection:
            connection.rollback()
        print(f"Database error in upsert_rpos_login: {error}")
        return jsonify({
            'error': 'Database error',
            'message': str(error)
        }), 500
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()


@app.route('/api/rpos-login/status', methods=['GET'])
def get_rpos_login_status():
    """Return approval status for a device/admin combination."""
    device_id = request.args.get('device_id')
    admin_employee_id = request.args.get('admin_employee_id')

    if not device_id:
        return jsonify({
            'error': 'Bad request',
            'message': 'device_id query parameter is required'
        }), 400

    connection = None
    cursor = None

    base_query = f"""
        SELECT APPROVAL_FLAG, EMPLOYEE_ID, ADMIN_EMPLOYEE_ID, LAN_IP
        FROM {RPOS_LOGIN_TABLE}
        WHERE DEVICE_ID = :device_id
    """

    params = {'device_id': device_id}

    if admin_employee_id:
        base_query += " AND ADMIN_EMPLOYEE_ID = :admin_employee_id"
        params['admin_employee_id'] = admin_employee_id

    try:
        connection = get_db_connection()
        cursor = connection.cursor()
        cursor.execute(base_query, params)
        result = cursor.fetchone()

        if result:
            return jsonify({
                'found': True,
                'approval_flag': result[0],
                'employee_id': result[1],
                'admin_employee_id': result[2],
                'lan_ip': result[3],
            }), 200

        return jsonify({
            'found': False,
            'approval_flag': 'N'
        }), 404

    except cx_Oracle.Error as error:
        print(f"Database error in get_rpos_login_status: {error}")
        return jsonify({
            'error': 'Database error',
            'message': str(error)
        }), 500
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()

@app.route('/api/user/search', methods=['POST'])
def search_user():
    """Search user by Employee Code (POST method)"""
    try:
        data = request.get_json()
        employee_code = data.get('employee_code')
        
        if not employee_code:
            return jsonify({
                'error': 'Bad request',
                'message': 'Employee code is required'
            }), 400
        
        # Convert to int if it's a string
        try:
            employee_code = int(employee_code)
        except ValueError:
            return jsonify({
                'error': 'Bad request',
                'message': 'Employee code must be a number'
            }), 400
        
        # Reuse the GET endpoint logic
        return get_user_by_employee_code(employee_code)
        
    except Exception as error:
        print(f"Error in search_user: {error}")
        return jsonify({
            'error': 'Internal server error',
            'message': str(error)
        }), 500

@app.route('/api/location/<int:location_code>', methods=['GET'])
def get_location_by_code(location_code):
    """Fetch location details from LOCATIONMASTER table based on Location Code"""
    connection = None
    cursor = None
    
    try:
        connection = get_db_connection()
        cursor = connection.cursor()
        
        # Query to fetch location details
        query = """
            SELECT 
                LOCATIONCODE,
                LOCATIONNAME,
                ADDRESS,
                FAX,
                EMAILID,
                MANAGER
            FROM LOCATIONMASTER
            WHERE LOCATIONCODE = :location_code
        """
        
        cursor.execute(query, {'location_code': location_code})
        result = cursor.fetchone()
        
        if result:
            # Map database columns to response
            location_data = {
                'location_code': result[0] if result[0] else None,
                'location_name': result[1] if result[1] else '',      # LOCATIONNAME -> Shop Name
                'address': result[2] if result[2] else '',          # ADDRESS -> Shop Address
                'fax': result[3] if result[3] else '',               # FAX -> Shop Phone
                'email_id': result[4] if result[4] else '',         # EMAILID -> Email ID
                'manager': result[5] if result[5] else '',           # MANAGER -> Manager
                'found': True
            }
            return jsonify(location_data), 200
        else:
            return jsonify({
                'found': False,
                'message': f'Location with code {location_code} not found'
            }), 404
            
    except cx_Oracle.Error as error:
        print(f"Database error: {error}")
        return jsonify({
            'error': 'Database error',
            'message': str(error)
        }), 500
    except Exception as error:
        print(f"Unexpected error: {error}")
        return jsonify({
            'error': 'Internal server error',
            'message': str(error)
        }), 500
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()


@app.route('/api/itemmaster/details', methods=['GET'])
def get_itemmaster_details():
    """Fetch catalog items from ITEMMASTERDETAILS view with optional filters."""
    fields_param = (request.args.get('fields') or '').strip()
    search_param = (request.args.get('search') or '').strip()
    limit_param = request.args.get('limit', default=100, type=int)
    offset_param = request.args.get('offset', default=0, type=int)

    selected_columns = []
    if fields_param:
        requested = [part.strip().upper() for part in fields_param.split(',')]
        selected_columns = [
            column for column in requested if column in ITEMMASTER_ALLOWED_COLUMNS
        ]

    if not selected_columns:
        selected_columns = list(ITEMMASTER_DEFAULT_COLUMNS)

    limit = max(1, min(limit_param or 100, 500))
    offset = max(0, offset_param or 0)

    column_sql = ', '.join(selected_columns)
    base_query = f"SELECT {column_sql}, ROW_NUMBER() OVER (ORDER BY ITEMNAME) AS RN FROM ITEMMASTERDETAILS"

    params = {}
    conditions = []

    if search_param:
        params['search'] = f"%{search_param.lower()}%"
        conditions.append(
            "("
            "LOWER(ITEMNAME) LIKE :search OR "
            "LOWER(ITEMNAMEARA) LIKE :search OR "
            "LOWER(BARCODE) LIKE :search"
            ")"
        )

    if conditions:
        base_query += " WHERE " + " AND ".join(conditions)

    query = (
        f"SELECT {column_sql} FROM ("
        f"{base_query}"
        ") WHERE RN > :offset AND RN <= :offset + :limit"
    )
    params['offset'] = offset
    params['limit'] = limit

    connection = None
    cursor = None

    try:
        connection = get_db_connection()
        cursor = connection.cursor()
        cursor.execute(query, params)
        rows = cursor.fetchall()
        column_names = [desc[0] for desc in cursor.description]

        items = []
        for row in rows:
            item = {
                column: _serialise_value(value)
                for column, value in zip(column_names, row)
            }
            items.append(item)

        return jsonify(
            {
                'data': items,
                'count': len(items),
                'limit': limit,
                'offset': offset,
                'fields': column_names,
            }
        ), 200

    except cx_Oracle.Error as error:
        print(f"Database error when fetching itemmaster details: {error}")
        return (
            jsonify(
                {
                    'error': 'Database error',
                    'message': str(error),
                }
            ),
            500,
        )
    except Exception as error:
        print(f"Unexpected error when fetching itemmaster details: {error}")
        return (
            jsonify(
                {
                    'error': 'Internal server error',
                    'message': str(error),
                }
            ),
            500,
        )
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()


if __name__ == '__main__':
    # Run the Flask server
    # Change host and port as needed
    app.run(host='0.0.0.0', port=5010, debug=True)

