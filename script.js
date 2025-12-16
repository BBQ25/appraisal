// Client-side validation for registration form
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.querySelector('form[action="register.php"]');
    if (registerForm) {
        registerForm.addEventListener('submit', function(event) {
            const employeeNameField = registerForm.querySelector('#employee_name');
            const employeeIdField = registerForm.querySelector('#employee_id');
            const employeeName = employeeNameField ? employeeNameField.value.trim() : '';
            const employeeId = employeeIdField ? employeeIdField.value.trim() : '';

            if (employeeName.length < 3) {
                alert('Employee Name must be at least 3 characters long.');
                event.preventDefault();
                return;
            }

            if (employeeId.length < 1) {
                alert('Employee ID cannot be empty.');
                event.preventDefault();
                return;
            }
        });
    }

    // Client-side validation for login form (optional, can be added later if needed)
    const loginForm = document.querySelector('form[action="login.php"]');
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            const employeeId = loginForm.querySelector('#employee_id').value;

            if (employeeId.length === 0) {
                alert('Please enter your Employee ID.');
                event.preventDefault();
                return;
            }
        });
    }
});
