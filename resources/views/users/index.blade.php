@extends('layout.main')
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

        <a href="{{ route('users.create') }}" class="btn btn-primary mb-3"><i class="bi bi-plus"></i> Add New User</a>
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
                        <td class="d-flex gap-2">
                            <!-- Edit Button -->
                            <a href="{{ route('users.edit', $user->id) }}"
                                class="btn btn-warning btn-sm d-flex align-items-center">
                                <i class="fas fa-edit mr-2"></i> Edit
                            </a>

                            <!-- Delete User Button with SweetAlert -->
                            <form action="{{ route('users.destroy', $user->id) }}" method="POST"
                                id="delete-form-{{ $user->id }}">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-danger btn-sm d-flex align-items-center"
                                    onclick="confirmDelete({{ $user->id }})">
                                    <i class="fas fa-trash-alt mr-2"></i> Delete
                                </button>
                            </form>

                            <!-- Reset Password Button -->
                            <button type="button" class="btn btn-secondary btn-sm d-flex align-items-center"
                                onclick="resetPassword({{ $user->id }}, '{{ $user->name }}')">
                                <i class="fas fa-key mr-2"></i> Reset Password
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

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



        function confirmDelete(userId) {
            // Show SweetAlert confirmation
            Swal.fire({
                title: 'Are you sure?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Find the form dynamically based on userId
                    const form = document.getElementById(`delete-form-${userId}`);

                    // Check if form exists and submit it
                    if (form) {
                        form.submit();
                    }
                }
            });
        }
    </script>
@endsection

 