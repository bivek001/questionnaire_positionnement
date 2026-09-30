<?php
require_once __DIR__.'/safe_rich_content.php';
function pkg_recipient(): array {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($script, '/admin/') && !empty($_SESSION['admin_id'])) return ['admin', 0];
    if (str_contains($script, '/professor/') && !empty($_SESSION['professor_id'])) return ['professor', (int)$_SESSION['professor_id']];
    if (!empty($_SESSION['trainee_id'])) return ['trainee', (int)$_SESSION['trainee_id']];
    return ['', 0];
}
function pkg_notifications(PDO $pdo): void {
    [$role,$id] = pkg_recipient();
    if (!$role) return;
    try {
        $s=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE recipient_type=? AND recipient_id=? AND read_at IS NULL');
        $s->execute([$role,$id]); $count=(int)$s->fetchColumn();
    }
    catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1146) throw $e;
        echo '<p role="status">'.qp_h(t('pkg_notifications_unavailable')).'</p>'; return;
    }
    if (empty($_SESSION['notification_csrf'])) $_SESSION['notification_csrf']=bin2hex(random_bytes(32));
    $s=$pdo->prepare('SELECT id,type,title,message,url,read_at,created_at FROM notifications WHERE recipient_type=? AND recipient_id=? ORDER BY id DESC LIMIT 20');
    $s->execute([$role,$id]);
    $endpoint=$role==='trainee'?'notifications.php':'../notifications.php';
    echo '<details class="qp-notifications"><summary>'.qp_h(t('pkg_notifications')).' <strong aria-label="'.qp_h(t('pkg_unread')).'">'.$count.'</strong></summary>';
    foreach($s as $n){
        echo '<article><strong>'.qp_h(t($n['title'])).'</strong><p>'.qp_h(t($n['message'])).'</p><small>'.qp_h($n['created_at']).'</small>';
        $targets=['admin'=>['account'=>'trainees.php','access'=>'professors.php','content'=>'manage_content.php','completion'=>'results.php','grading'=>'results.php','assignment'=>'assign_questionnaire.php'],'professor'=>['account'=>'index.php','access'=>'themes.php','completion'=>'trainees.php','grading'=>'trainees.php','assignment'=>'trainees.php'],'trainee'=>['account'=>'index.php','assignment'=>'assigned_questionnaires.php','completion'=>'my_results.php','grading'=>'my_results.php']];
        $target=$targets[$role][$n['type']] ?? null;
        // Accept only a role-specific, relative attempt link; endpoint rechecks authorization.
        if ($role==='professor' && preg_match('~^professor/attempt_details\.php\?id=[1-9][0-9]*$~D', $n['url'] ?? '')) $target=substr($n['url'],10);
        if($target !== null) echo '<p><a href="'.qp_h($target).'">'.qp_h(t('pkg_open')).'</a></p>';
        if ($n['read_at']===null) pkg_notification_form($endpoint,$role,(int)$n['id'],t('pkg_mark_read'));
        echo '</article>';
    }
    pkg_notification_form($endpoint,$role,0,t('pkg_mark_all'));
    echo '</details>';
}
function pkg_notification_form(string $endpoint,string $role,int $id,string $label): void {
    echo '<form action="'.qp_h($endpoint).'" method="post"><input type="hidden" name="role" value="'.qp_h($role).'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="csrf_token" value="'.qp_h($_SESSION['notification_csrf']).'"><button>'.qp_h($label).'</button></form>';
}
