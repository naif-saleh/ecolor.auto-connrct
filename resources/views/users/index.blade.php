@extends('layout.master')
@section('title', '`Users')

@section('content')
    <div class="container">
        <h2>Users</h2>

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <a href="{{ route('users.create') }}" class="btn btn-primary mb-3">Add New User</a>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role }}</td>
                        <td>
                            <a href="{{ route('users.edit', $user->id) }}" class="btn btn-warning btn-sm">Edit</a>
                            <!-- Delete User Form -->
                            <form action="{{ route('users.destroy', $user->id) }}" method="POST"
                                class="d-inline delete-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm"
                                    onclick="confirmDelete(event, this.closest('form'))">Delete</button>
                            </form>

                            <!-- Reset Password Button with SweetAlert -->
                            <a href="javascript:void(0);"
                                onclick="resetPassword({{ $user->id }}, '{{ $user->name }}')"
                                class="btn btn-secondary btn-sm">Reset Password</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <script>
        function resetPassword(userId, userName) {
            // SweetAlert2 Input Modal
            Swal.fire({
                title: `Reset Password for ${userName}`,
                input: 'password', // Password input type
                inputPlaceholder: 'Enter new password',
                inputAttributes: {
                    'aria-label': 'Password for user ' + userName
                },
                showCancelButton: true,
                confirmButtonText: 'Reset Password',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                customClass: {
                    confirmButton: 'btn btn-success'
                },
                preConfirm: (newPassword) => {
                    if (!newPassword || newPassword.length < 8) {
                        Swal.showValidationMessage('Password must be at least 8 characters long');
                        return false;
                    }

                    // AJAX request to update password
                    return fetch(`/users/reset-password`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content')
                            },
                            body: JSON.stringify({
                                user_id: userId,
                                new_password: newPassword
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Success!', 'Password has been reset successfully.', 'success');
                            } else {
                                Swal.fire('Error!', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error!', 'Something went wrong, please try again.', 'error');
                        });
                },
                allowOutsideClick: () => !Swal.isLoading() // Close popup when not loading
            });
        }






        function confirmDelete(event, form) {
            event.preventDefault(); // Prevent the form from being submitted immediately

            Swal.fire({
                title: 'Are you sure?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'No, cancel!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // Submit the form if the user confirmed the deletion
                }
            });
        }
    </script>
@endsection
