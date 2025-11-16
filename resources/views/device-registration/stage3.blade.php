@extends('device-registration.layout', [
    'stages' => $stages,
    'currentStage' => 2,
])

@section('content')
    <h1>User Configuration</h1>
    <p class="description">Create a user that will access this device after approval.</p>

    <form method="POST" action="{{ route('registration.stage2.store') }}">
        @csrf

        <div>
            <label for="employee_id">Employee ID</label>
            <input
                id="employee_id"
                name="employee_id"
                type="text"
                value="{{ old('employee_id', $form['employee_id'] ?? '') }}"
                required
                autocomplete="off"
            >
            @error('employee_id')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div id="employee_name_container" style="display: none;">
            <label>Employee Name</label>
            <div class="employee-name-display" style="padding: 0.85rem 0.95rem; background: #f1f5f9; border: 1px solid #d0d5dd; border-radius: 10px; color: #1c1d21; font-size: 1rem;">
                <span id="employee_name_text"></span>
            </div>
        </div>

        <div>
            <label for="username">Username</label>
            <input
                id="username"
                name="username"
                type="text"
                value="{{ old('username', $form['username'] ?? '') }}"
                readonly
                required
            >
            @error('username')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                required
            >
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation">Confirm Password</label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
            >
        </div>

        <div class="actions">
            <a href="{{ route('registration.stage1') }}" class="btn btn-secondary">Back</a>
            <button type="submit" class="btn btn-primary">Continue</button>
        </div>
    </form>
@endsection

@section('scripts')
    <script>
        const employeeInput = document.getElementById('employee_id');
        const usernameInput = document.getElementById('username');
        const employeeNameContainer = document.getElementById('employee_name_container');
        const employeeNameText = document.getElementById('employee_name_text');
        
        let debounceTimer;

        const fetchEmployeeData = async (employeeId) => {
            if (!employeeId || employeeId.trim() === '') {
                employeeNameContainer.style.display = 'none';
                usernameInput.value = '';
                return;
            }

            try {
                const response = await fetch(`{{ route('registration.employee.get', ['employeeId' => '__ID__']) }}`.replace('__ID__', encodeURIComponent(employeeId)));
                
                if (!response.ok) {
                    console.error('HTTP error:', response.status, response.statusText);
                    employeeNameContainer.style.display = 'none';
                    usernameInput.value = '';
                    return;
                }
                
                const data = await response.json();
                console.log('Employee data response:', data);

                if (data.success && data.employee && data.employee.name) {
                    employeeNameText.textContent = data.employee.name;
                    employeeNameContainer.style.display = 'block';
                    usernameInput.value = data.username || employeeId; // Username = EMPLOYEECODE
                    console.log('✓ Employee data loaded:', data.employee.name);
                } else {
                    console.warn('Employee not found or invalid response:', data);
                    employeeNameContainer.style.display = 'none';
                    usernameInput.value = '';
                }
            } catch (error) {
                console.error('Error fetching employee data:', error);
                employeeNameContainer.style.display = 'none';
                usernameInput.value = '';
            }
        };

        employeeInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            const employeeId = e.target.value.trim();
            
            debounceTimer = setTimeout(() => {
                fetchEmployeeData(employeeId);
            }, 300);
        });

        // Fetch on page load if employee ID is already filled
        if (employeeInput.value) {
            fetchEmployeeData(employeeInput.value);
        }
    </script>
@endsection

