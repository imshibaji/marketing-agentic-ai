<!DOCTYPE html>
<html lang="en" data-theme="dark">
<?= $this->include('layout/partials/head') ?>
<body>

    <!-- MAIN APP -->
    <div class="app-container" id="main-app">
        
        <!-- Header -->
        <?= $this->include('layout/partials/header') ?>

        <!-- Main Workspace Grid -->
        <div class="dashboard-grid <?= ($activeTab !== 'campaigns') ? 'sidebar-hidden' : '' ?>">
            
            <!-- Sidebar -->
            <?= $this->include('layout/partials/sidebar') ?>

            <!-- Right Workspace Pane -->
            <main class="workspace">
                <?= $this->renderSection('content') ?>
            </main>
        </div>

    </div>

    <!-- Modals -->
    <?= $this->include('layout/partials/settings_modal') ?>
    <?= $this->include('layout/partials/profile_modal') ?>
    <?= $this->include('layout/partials/manual_lead_modal') ?>
    <?= $this->include('layout/partials/share_campaign_modal') ?>

    <!-- Scripts -->
    <?= $this->include('layout/partials/scripts') ?>

</body>
</html>
