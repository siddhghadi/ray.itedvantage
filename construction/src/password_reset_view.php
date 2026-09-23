<?php
declare(strict_types=1);
$resetUser=null;
if((string)($_GET['action']??'')==='edit'&&($resetUserId=(string)($_GET['id']??''))!==''){
    $resetQuery=$db->prepare('SELECT id,name,email FROM users WHERE id=? AND company_id=?');
    $resetQuery->execute([$resetUserId,$user['company_id']]);
    $resetUser=$resetQuery->fetch();
}
?>
<style>.password-reset-card{margin:24px auto 0}.password-reset-card .form-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.password-reset-card .field{margin:4px 0 16px}.password-reset-card .button{margin-top:4px}@media(max-width:650px){.password-reset-card{margin-top:18px}.password-reset-card .form-grid{grid-template-columns:1fr}}</style>
<?php if($resetUser&&can($db,$user,'users.update')):?><form class="form-card user-editor password-reset-card" method="post"><div class="editor-head"><div><span class="eyebrow">SECURITY</span><h2>Reset password</h2><p class="muted">Create a temporary password and share it securely with <?=e($resetUser['name'])?>.</p></div></div><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="user_id" value="<?=e($resetUser['id'])?>"><div class="form-grid"><label class="field">New temporary password<input type="password" name="new_password" minlength="10" autocomplete="new-password" required></label><label class="field">Confirm temporary password<input type="password" name="confirm_password" minlength="10" autocomplete="new-password" required></label></div><button class="button" name="reset_user_password" value="1">Reset password</button></form><?php endif;?>
