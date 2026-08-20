<div class="workspace-shell it-workspace">
    <aside class="side-nav td-side-wide">
        <a class="side-logo ray-wordmark" href="./" aria-label="Ray CRM home">RAY</a>
        <nav aria-label="ITedvantage navigation">
            <a class="active" href="?business=itedvantage"><small>Add Blog</small></a>
        </nav>
        <button class="theme-toggle" data-theme-toggle type="button" aria-label="Switch light and dark mode" title="Light / dark mode"><span aria-hidden="true">☼</span><small>Theme</small></button>
        <a class="side-bottom" href="./" title="All businesses">⌘</a>
    </aside>
    <main class="td-dashboard">
        <nav class="desktop-menu" aria-label="ITedvantage desktop navigation"><a class="desktop-brand" href="./">RAY</a><div><a class="active" href="?business=itedvantage">Add Blog</a></div><button class="theme-toggle desktop-theme-toggle" data-theme-toggle type="button" aria-label="Switch light and dark mode"><span aria-hidden="true">☼</span><small>Theme</small></button></nav>
        <header class="td-header">
            <div><a class="back-link" href="./">← All businesses</a><span class="eyebrow">ITEDVANTAGE / CONTENT</span><h1>Add Blog</h1><p>Write once, preview clearly, and upload to WordPress after approval.</p></div>
            <div class="header-actions"><span class="soft-badge">Layout mode</span></div>
        </header>

        <section class="blog-builder">
            <article class="panel blog-editor">
                <div class="panel-heading"><div><span class="eyebrow">BLOG DETAILS</span><h2>Create your post</h2></div><span class="soft-badge">Not saved</span></div>
                <form class="stack-form" id="blog-form">
                    <label for="blog-title">Blog title</label>
                    <input id="blog-title" type="text" maxlength="180" placeholder="Enter a clear blog title">

                    <label for="featured-image">Featured image</label>
                    <input id="featured-image" type="file" accept="image/png,image/jpeg,image/webp">

                    <label for="additional-images">Additional images</label>
                    <input id="additional-images" type="file" accept="image/png,image/jpeg,image/webp" multiple>

                    <label for="blog-content">Blog content</label>
                    <textarea id="blog-content" rows="14" placeholder="Write or paste the complete blog content here…"></textarea>

                    <div class="form-split"><div><label for="blog-category">Category</label><input id="blog-category" type="text" placeholder="Technology"></div><div><label for="blog-tags">Tags</label><input id="blog-tags" type="text" placeholder="AI, software, guides"></div></div>

                    <span class="form-divider">Search appearance</span>
                    <label for="seo-title">SEO title</label>
                    <input id="seo-title" type="text" maxlength="60" placeholder="SEO title shown on Google">
                    <label for="seo-description">SEO description</label>
                    <textarea id="seo-description" rows="3" maxlength="160" placeholder="Short description for search results"></textarea>

                    <button class="primary-button upload-wordpress" type="button" disabled>Upload to WordPress</button>
                    <small class="connection-note">WordPress connection will be activated after the blog template is finalized.</small>
                </form>
            </article>

            <article class="panel blog-preview-panel">
                <div class="panel-heading"><div><span class="eyebrow">LIVE PREVIEW</span><h2>Template preview</h2></div><span class="preview-device">Desktop</span></div>
                <div class="blog-preview" id="blog-preview">
                    <div class="preview-cover" id="preview-cover"><span>Featured image</span></div>
                    <div class="preview-body">
                        <span class="preview-category" id="preview-category">CATEGORY</span>
                        <h1 id="preview-title">Your blog title will appear here</h1>
                        <p class="preview-meta">ITedvantage · Draft preview</p>
                        <div class="preview-copy" id="preview-content">Start writing in the editor. Your content will appear here using the selected blog template.</div>
                        <div class="preview-gallery" id="preview-gallery"></div>
                    </div>
                </div>
            </article>
        </section>
    </main>
</div>
