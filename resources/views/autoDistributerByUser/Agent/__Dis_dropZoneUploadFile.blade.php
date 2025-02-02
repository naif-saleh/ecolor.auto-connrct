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
          <form action="/upload" class="dropzone" id="fileDropzone">
            <div class="dz-message">
              Drag & Drop your file here or click to select
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
