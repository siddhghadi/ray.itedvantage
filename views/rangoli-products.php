<?php
$productEditId = (string) ($_GET['edit'] ?? '');
$productEditing = null;
foreach ($rangoliProducts as $candidate) if ((string) $candidate['id'] === $productEditId) { $productEditing = $candidate; break; }
$showProductForm = isset($_GET['new']) || $productEditing !== null;
$productValues = $productEditing ?? ['name'=>'','category'=>'','sku'=>'','height'=>'','width'=>'','unit'=>'in','retail_price'=>'','stock'=>0,'cost_price'=>0,'dealer_price'=>0,'low_stock'=>3];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_catalogue_product'])) {
    foreach (array_keys($productValues) as $key) if (isset($_POST[$key]) && is_scalar($_POST[$key])) $productValues[$key] = (string) $_POST[$key];
    $showProductForm = true;
}
$productCategories = array_values(array_unique(array_map(static fn($p) => trim((string) ($p['category'] ?? '')) ?: 'Uncategorised', $rangoliProducts)));
natcasesort($productCategories);
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section id="wr-products" class="wr-products">
<?php if (isset($_GET['import'])): ?>
    <a class="back-link" href="?business=rangoli&amp;page=products">← Back to products</a>
    <article class="panel"><h2>Import products</h2><p>Paste tab-separated columns: name, sku, category, retail_price, height, width. Include the headings. Sizes are in inches. Leave unknown prices and sizes empty. Existing product names are skipped; photos, stock and dealer prices are kept.</p>
    <form method="post" class="stack-form"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['csrf']) ?>"><input type="hidden" name="import_catalogue_products" value="1">
    <label for="wr-import">Product rows</label><textarea id="wr-import" name="product_rows" rows="16" required><?= $escape($_POST['product_rows'] ?? '') ?></textarea><button class="primary-button" type="submit">Import products</button></form></article>
<?php elseif ($showProductForm): ?>
    <a class="back-link" href="?business=rangoli&amp;page=products">← Back to products</a>
    <article class="panel wr-product-form"><h2><?= $productEditing ? 'Edit product' : 'Add product' ?></h2>
    <form method="post" id="wr-product-form" class="stack-form">
        <input type="hidden" name="csrf" value="<?= $escape($_SESSION['csrf']) ?>">
        <input type="hidden" name="save_catalogue_product" value="1">
        <input type="hidden" name="product_id" value="<?= $escape($productEditing['id'] ?? '') ?>">
        <input type="hidden" name="revision" value="<?= $escape($_POST['revision'] ?? ($productEditing ? rangoliProductRevision($productEditing) : '')) ?>">
        <input type="hidden" name="image_data" id="wr-image-data" value="">
        <label for="wr-photo">Product photo (optional)</label><input type="file" id="wr-photo" accept="image/jpeg,image/png,image/webp">
        <small class="panel-note">You can add a photo later. JPG, PNG or WebP, up to 15 MB.</small>
        <img id="wr-photo-preview" class="wr-photo-preview" alt="Product photo" <?= empty($productEditing['image']) ? 'hidden' : 'src="'.$escape($productEditing['image']).'"' ?>>
        <label for="wr-name">Product name</label><input id="wr-name" name="name" maxlength="120" required value="<?= $escape($productValues['name']) ?>">
        <label for="wr-category">Category</label><input id="wr-category" name="category" list="wr-categories" maxlength="80" required placeholder="Choose or type a category" value="<?= $escape($productValues['category'] ?? '') ?>">
        <datalist id="wr-categories"><?php foreach($productCategories as $category): ?><option value="<?= $escape($category) ?>"><?php endforeach; ?></datalist>
        <label for="wr-sku">SKU / Product code</label><input id="wr-sku" name="sku" maxlength="50" required value="<?= $escape($productValues['sku']) ?>">
        <div class="wr-measurements"><div><label for="wr-height">Height (optional)</label><input id="wr-height" name="height" type="number" min=".001" max="100000" step="any" value="<?= $escape($productValues['height'] ?? '') ?>"></div><div><label for="wr-width">Width (optional)</label><input id="wr-width" name="width" type="number" min=".001" max="100000" step="any" value="<?= $escape($productValues['width'] ?? '') ?>"></div><div><label for="wr-unit">Unit</label><select id="wr-unit" name="unit"><?php foreach(['in'=>'Inches','cm'=>'cm','mm'=>'mm','ft'=>'Feet'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($productValues['unit']??'in')===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div></div>
        <div class="form-split"><div><label for="wr-retail">Selling price (₹, optional)</label><input id="wr-retail" name="retail_price" type="number" min="0" max="10000000" step=".01" value="<?= $escape($productValues['retail_price']) ?>"></div><div><label for="wr-stock">Stock available</label><input id="wr-stock" name="stock" type="number" min="0" max="1000000" step="1" required value="<?= $escape($productValues['stock']) ?>"></div></div>
        <details><summary>More options: dealer price</summary><label for="wr-dealer">Dealer price (₹, optional)</label><input id="wr-dealer" name="dealer_price" type="number" min="0" max="10000000" step=".01" value="<?= $escape($productValues['dealer_price']) ?>"><small class="panel-note">For your CRM only. The PDF shows the selling price.</small></details>
        <p id="wr-form-status" role="status" aria-live="polite"></p><div class="wr-actions"><a class="small-button button-link" href="?business=rangoli&amp;page=products">Cancel</a><button id="wr-save" class="primary-button" type="submit">Save product</button></div>
    </form></article>
<?php else: ?>
    <div class="wr-filters"><input id="wr-search" type="search" placeholder="Find a product by name or SKU" aria-label="Find a product"><select id="wr-filter" aria-label="Filter by category"><option value="">All categories</option><?php foreach($productCategories as $category): ?><option><?= $escape($category) ?></option><?php endforeach; ?></select></div>
    <div class="panel wr-list-panel"><div class="lead-table-wrap"><table class="lead-table wr-table"><thead><tr><th><input type="checkbox" id="wr-all" aria-label="Select all shown products"></th><th>Product name</th><th>Category</th><th>SKU</th><th>Height × Width</th><th>Price</th><th>Stock</th></tr></thead><tbody id="wr-product-rows">
    <?php foreach($rangoliProducts as $product): $metadata = array_intersect_key($product,array_flip(['id','name','sku','category','height','width','unit','retail_price'])); $category=trim((string)($product['category']??''))?:'Uncategorised'; ?>
        <tr data-product="<?= $escape(json_encode($metadata,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)) ?>" data-category="<?= $escape($category) ?>"><td><input class="wr-select" type="checkbox" aria-label="Select <?= $escape($product['name']) ?>"></td><td><a class="wr-product-link" href="?business=rangoli&amp;page=products&amp;edit=<?= rawurlencode((string)$product['id']) ?>"><?php if(!empty($product['image'])): ?><img src="<?= $escape($product['image']) ?>" alt="" loading="lazy"><?php else: ?><span class="wr-no-photo">Add<br>photo</span><?php endif; ?><strong><?= $escape($product['name']) ?></strong></a></td><td><?= $escape($category) ?></td><td><?= $escape($product['sku']?:'—') ?></td><td><?= !empty($product['height'])||!empty($product['width'])?$escape(($product['height']?:'—').' × '.($product['width']?:'—').' '.($product['unit']??'in')):'—' ?></td><td><?= isset($product['retail_price']) ? '₹'.number_format((float)$product['retail_price'],0) : 'Not set' ?><?php if(!empty($product['dealer_price'])): ?><small>Dealer: ₹<?= number_format((float)$product['dealer_price'],0) ?></small><?php endif; ?></td><td><?= (int)$product['stock'] ?></td></tr>
    <?php endforeach; ?></tbody></table></div><div id="wr-empty" class="empty-state compact" <?= $rangoliProducts?'hidden':'' ?>><h3><?= $rangoliProducts?'No matching products':'Add your first product' ?></h3><p><?= $rangoliProducts?'Try another name or category.':'Use the Add product button above to get started.' ?></p></div></div>
    <div class="wr-selection"><div><strong id="wr-selection-count" aria-live="polite">No products selected</strong><small>Select the boxes beside your products.</small></div><div class="wr-actions"><button class="small-button" id="wr-clear" type="button" hidden>Clear selection</button><button class="primary-button" id="wr-make-pdf" type="button" disabled>Make PDF</button></div></div><p id="wr-export-status" role="status" aria-live="polite"></p>
    <dialog id="wr-pdf-dialog" class="wr-pdf-dialog" aria-labelledby="wr-pdf-title"><div class="wr-dialog-header"><div><h2 id="wr-pdf-title">Your catalogue</h2><p>Each category starts on a new page.</p></div><button type="button" class="small-button" id="wr-close-pdf">Close</button></div><div id="wr-pdf-pages" class="wr-pdf-pages"></div><div class="wr-download-actions"><a id="wr-download-pdf" class="primary-button button-link" download="Woolen-Rangoli.pdf">Download PDF</a></div></dialog>
<?php endif; ?>
</section>
