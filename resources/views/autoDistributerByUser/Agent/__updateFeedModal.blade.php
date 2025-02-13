<div id="feedModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3 shadow-lg">
            <div class="modal-header border-bottom-0">

                {{-- <button type="button" class="close text-muted" data-dismiss="modal">&times;</button> --}}
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check mb-0">
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="form-check-input">
                        <label for="selectAll" class="form-check-label text-muted"><strong>Select All</strong></label>
                    </div>
                    <div class="text-muted">
                        <small>Click to select or deselect all items</small>
                    </div>
                </div>

                <!-- Feed Table -->
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Select</th>
                            <th scope="col">Extension</th>
                            <th scope="col">From</th>
                            <th scope="col">To</th>
                            <th scope="col">Allow</th>
                            <th scope="col">Created At</th>

                        </tr>
                    </thead>
                    <tbody id="feedList">
                        <!-- Feed items will be dynamically injected here -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-top-0 d-flex justify-content-between">
                <button class="btn btn-outline-success rounded-pill px-4" onclick="updateStatus(1)">
                    <i class="fa fa-check-circle"></i> Mark as On
                </button>
                <button class="btn btn-outline-danger rounded-pill px-4" onclick="updateStatus(0)">
                    <i class="fa fa-times-circle"></i> Mark as Off
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS Styling for Better UI -->
<style>
    .modal-content {
        background-color: #f9f9f9;
        border-radius: 10px;
        overflow: hidden;
    }
    .modal-header {
        background-color: #007bff;
        color: white;
        font-weight: bold;
    }
    .modal-body {
        padding: 30px;
        color: #444;
    }
    .table th, .table td {
        text-align: center;
        padding: 12px;
    }
    .table th {
        background-color: #007bff;
        color: white;
    }
    .table tbody tr:hover {
        background-color: #f1f1f1;
    }
    .form-check-label {
        font-size: 16px;
    }
    .modal-footer {
        background-color: #f1f1f1;
    }
    .btn-outline-success,
    .btn-outline-danger {
        width: 48%;
    }
    .btn-outline-success:hover {
        background-color: #28a745;
        color: white;
    }
    .btn-outline-danger:hover {
        background-color: #dc3545;
        color: white;
    }
</style>
