 @extends('layout.dropZone')
<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload Your File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('autodailers.file.csv.dropzone.upload') }}" class="dropzone" id="fileDropzone">
                    @csrf
                    <div class="dz-message">
                        Drag & Drop your file here or click to select
                    </div>
                </form>

                <!-- Progress Bar (Added Inside Modal) -->
                <div id="uploadProgress"
                    style="display: none; width: 100%; background: #ddd; border-radius: 5px; margin-top: 15px;">
                    <div id="uploadProgressBar"
                        style="width: 0%; height: 20px; background: #28a745; border-radius: 5px; text-align: center; color: white; font-weight: bold;">
                        0%
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
