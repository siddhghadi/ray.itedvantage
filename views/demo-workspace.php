<?php
$workspaceTitle = ['construction'=>'Construction','hospitality'=>'Hospitality (Hotels)','insurance'=>'Insurance'][$view];
?>
<main class="td-dashboard">
    <header class="td-header"><div><a class="back-link" href="./">← All businesses</a><span class="eyebrow">RAY / DEMO WORKSPACES</span><h1><?= htmlspecialchars($workspaceTitle) ?></h1></div></header>
    <section class="panel">
        <?php if ($view === 'construction'): ?>
            <h2>Your existing construction workspace</h2>
            <p>The current application and data remain on the construction site. Its move into RAY is pending.</p>
            <a class="primary-button button-link" href="https://crm.itedvantage.com/">Open Construction →</a>
            <p class="panel-note">Uses your existing Construction login.</p>
        <?php else: ?>
            <h2>Ready for your master prompt</h2>
            <p>This workspace is reserved. Features and demo data will be added after your requirements are ready.</p>
        <?php endif; ?>
    </section>
</main>
