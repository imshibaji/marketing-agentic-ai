<?php if ($activeTab === 'campaigns'): ?>
<!-- Left Sidebar (Campaign History) -->
<aside class="sidebar">
    <h2>
        Campaigns
        <button class="action-icon-button" id="new-campaign-btn" title="Create New Campaign" style="width:28px; height:28px; font-size:12px;">
            <i class="fas fa-plus"></i>
        </button>
    </h2>
    <ul class="campaign-list" id="campaign-list">
        <!-- Loaded dynamically via JS -->
    </ul>
</aside>
<?php endif; ?>
