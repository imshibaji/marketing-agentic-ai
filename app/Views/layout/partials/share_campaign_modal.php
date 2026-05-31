<!-- Share Campaign Modal -->
<div class="modal-overlay" id="share-campaign-modal" style="z-index:10095;">
    <div class="modal-content" style="max-width:480px; padding:28px;">
        <div class="modal-header">
            <h3 style="font-size:18px;"><i class="fas fa-share-nodes" style="color:var(--accent-primary);"></i> Share Campaign</h3>
            <button class="modal-close" id="share-campaign-modal-close"><i class="fas fa-times"></i></button>
        </div>
        <p style="font-size:13px; color:var(--text-secondary); margin:8px 0 16px;">Select users to share this campaign with. They will be able to view and use this campaign but cannot delete it.</p>
        <div id="share-campaign-user-list" style="display:flex; flex-direction:column; gap:10px; max-height:320px; overflow-y:auto; padding-right:4px;">
            <!-- User checkboxes loaded dynamically -->
        </div>
        <div id="share-campaign-error" style="color:var(--accent-error); font-size:12px; display:none; margin-top:8px;"></div>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <button id="share-campaign-save-btn" class="btn-primary" style="flex:1; padding:10px; margin:0;">
                <i class="fas fa-save"></i> Save Sharing
            </button>
            <button id="share-campaign-cancel-btn" class="btn-secondary" style="padding:10px 16px;">
                Cancel
            </button>
        </div>
    </div>
</div>
