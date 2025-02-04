@if (session('inactive'))
        <script>
            window.onload = function() {
                Swal.fire({
                    title: 'File is Disactivited ⚠️',
                    text: "{{ session('success') }}",
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
            };
        </script>
    @endif
    @if (session('active'))
        <script>
            window.onload = function() {
                Swal.fire({
                    title: 'File is Activited ✅',
                    text: "{{ session('success') }}",
                    icon: 'success',
                    confirmButtonText: 'OK'
                });
            };
        </script>
    @endif
