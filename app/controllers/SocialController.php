<?php

class SocialController extends Controller
{
    private $accessRoles = ['super_admin', 'marketing'];
    private $accounts;

    public function __construct()
    {
        $this->accounts = new SocialAccount();
    }

    // A gestão das redes agora fica dentro da página de Métricas Sociais (buffer/dashboard).
    public function index()
    {
        $this->requireRole($this->accessRoles);
        $this->redirect('buffer/dashboard');
    }

    // Importar páginas do Facebook + contas Instagram do token Meta
    public function importMeta()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $api = new MetaApi();
        if (!$api->hasToken()) $this->json(['error' => 'Configure o token da Meta em Configurações.'], 400);

        $res = $api->getPages();
        if (!empty($res['error'])) $this->json(['error' => $res['error']['message'] ?? 'Erro ao consultar a Meta'], 400);

        $imported = 0;
        foreach (($res['data'] ?? []) as $page) {
            $this->accounts->upsert('facebook_page', $page['id'], [
                'display_name' => $page['name'] ?? null,
                'avatar' => $page['picture']['data']['url'] ?? null,
                'access_token' => $page['access_token'] ?? null,
                'followers' => $page['followers_count'] ?? ($page['fan_count'] ?? null),
            ]);
            $imported++;

            $ig = $page['instagram_business_account'] ?? null;
            if ($ig && !empty($ig['id'])) {
                $this->accounts->upsert('meta_instagram', $ig['id'], [
                    'display_name' => $ig['username'] ?? ($ig['name'] ?? null),
                    'username' => $ig['username'] ?? null,
                    'avatar' => $ig['profile_picture_url'] ?? null,
                    'access_token' => $page['access_token'] ?? null,
                    'followers' => $ig['followers_count'] ?? null,
                    'follows' => $ig['follows_count'] ?? null,
                    'media_count' => $ig['media_count'] ?? null,
                ]);
                $imported++;
            }
        }
        $this->json(['success' => true, 'imported' => $imported]);
    }

    // Adicionar organização do LinkedIn manualmente (ID/URN)
    public function addLinkedin()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $orgId = trim($_POST['org_id'] ?? '');
        if ($orgId === '') $this->json(['error' => 'Informe o ID/URN da organização do LinkedIn.'], 400);

        $name = trim($_POST['display_name'] ?? '');
        // Tenta buscar o nome real via API
        $api = new LinkedInApi();
        if ($api->hasToken()) {
            $org = $api->getOrganization($orgId);
            if (!empty($org['localizedName'])) $name = $org['localizedName'];
        }

        $id = $this->accounts->upsert('linkedin_org', preg_replace('/\D/', '', $orgId), [
            'display_name' => $name ?: ('LinkedIn ' . $orgId),
        ]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function delete($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->accounts->delete($id);
        $this->json(['success' => true]);
    }

    // Sincroniza seguidores, analytics e publicações (com interações) de todas as contas
    public function syncMetrics()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        @set_time_limit(180);
        $meta = new MetaApi();
        $linkedin = new LinkedInApi();
        $updated = 0;
        $errors = [];

        $since = !empty($_POST['start']) ? strtotime($_POST['start']) : strtotime('-30 days');
        $until = !empty($_POST['end']) ? strtotime($_POST['end']) : time();

        foreach ($this->accounts->all(true) as $acc) {
            try {
                if ($acc['provider'] === 'meta_instagram') {
                    $updated += $this->syncInstagram($acc, $since, $until, $errors);
                } elseif ($acc['provider'] === 'facebook_page') {
                    $updated += $this->syncFacebook($acc, $errors);
                } elseif ($acc['provider'] === 'linkedin_org') {
                    $updated += $this->syncLinkedin($acc, $errors);
                }
            } catch (\Throwable $e) {
                $errors[] = $acc['display_name'] . ': ' . $e->getMessage();
            }
        }

        $this->json(['success' => true, 'updated' => $updated, 'errors' => $errors]);
    }

    // ===== Sync helpers =====
    private function syncInstagram($acc, $since, $until, &$errors)
    {
        $api = $acc['access_token'] ? new MetaApi($acc['access_token']) : new MetaApi();
        if (!$api->hasToken()) { $errors[] = 'Meta sem token'; return 0; }

        $info = $api->getInstagramAccount($acc['external_id']);
        if (!empty($info['error'])) { $errors[] = 'IG: ' . $info['error']['message']; return 0; }

        $ins = MetaApi::sumInsights($api->getInstagramAccountInsights($acc['external_id'], $since, $until));

        // Publicações + interações
        $media = $api->getInstagramMedia($acc['external_id'], 25);
        $totLikes = 0; $totComments = 0; $totShares = 0; $postCount = 0;
        foreach (($media['data'] ?? []) as $m) {
            $mi = MetaApi::flattenMediaInsights($api->getInstagramMediaInsights($m['id'], $m['media_type'] ?? 'IMAGE'));
            $likes = $m['like_count'] ?? ($mi['likes'] ?? 0);
            $comments = $m['comments_count'] ?? ($mi['comments'] ?? 0);
            $shares = $mi['shares'] ?? 0;
            $totLikes += $likes; $totComments += $comments; $totShares += $shares; $postCount++;

            $this->accounts->upsertPost('meta_instagram', $m['id'], [
                'account_id' => $acc['id'],
                'post_type' => $m['media_type'] ?? null,
                'caption' => $m['caption'] ?? null,
                'permalink' => $m['permalink'] ?? null,
                'thumbnail' => $m['thumbnail_url'] ?? ($m['media_url'] ?? null),
                'published_at' => !empty($m['timestamp']) ? date('Y-m-d H:i:s', strtotime($m['timestamp'])) : null,
                'likes' => $likes,
                'comments' => $comments,
                'shares' => $shares,
                'saved' => $mi['saved'] ?? null,
                'reach' => $mi['reach'] ?? null,
                'video_views' => $mi['views'] ?? null,
                'engagement' => $mi['total_interactions'] ?? ($likes + $comments + $shares),
                'extra_json' => json_encode($mi),
            ]);
        }

        $followers = $info['followers_count'] ?? $acc['followers'];
        $engRate = ($followers && $postCount) ? round((($totLikes + $totComments) / max(1, $postCount)) / $followers * 100, 2) : null;

        $this->accounts->saveMetrics($acc['id'], [
            'display_name' => $info['username'] ?? $acc['display_name'],
            'username' => $info['username'] ?? $acc['username'],
            'avatar' => $info['profile_picture_url'] ?? $acc['avatar'],
            'followers' => $followers,
            'follows' => $info['follows_count'] ?? $acc['follows'],
            'media_count' => $info['media_count'] ?? $acc['media_count'],
            'reach' => $ins['reach'] ?? null,
            'impressions' => $ins['impressions'] ?? null,
            'profile_views' => $ins['profile_views'] ?? null,
            'total_likes' => $totLikes,
            'total_comments' => $totComments,
            'total_shares' => $totShares,
            'engagement_rate' => $engRate,
        ]);
        $this->saveAccountSnapshot($acc, 'instagram', [
            'followers' => $followers,
            'reach' => $ins['reach'] ?? null,
            'impressions' => $ins['impressions'] ?? null,
            'views' => null,
            'likes' => $totLikes,
            'comments' => $totComments,
            'shares' => $totShares,
            'posts_count' => $info['media_count'] ?? null,
            'engagement_rate' => $engRate,
        ]);
        return 1;
    }

    /** Salva um snapshot histórico (todos os dados) da conta direta. */
    private function saveAccountSnapshot($acc, $provider, $data)
    {
        try {
            (new SocialSnapshot())->save('direct', $provider, $acc['external_id'], $acc['display_name'] ?? null, $data);
        } catch (\Throwable $e) { /* ignora */ }
    }

    private function syncFacebook($acc, &$errors)
    {
        $api = $acc['access_token'] ? new MetaApi($acc['access_token']) : new MetaApi();
        if (!$api->hasToken()) { $errors[] = 'Meta sem token'; return 0; }

        $info = $api->getFacebookPage($acc['external_id']);
        if (!empty($info['error'])) { $errors[] = 'FB: ' . $info['error']['message']; return 0; }

        $posts = $api->getFacebookPosts($acc['external_id'], $acc['access_token'], 25);
        $totLikes = 0; $totComments = 0; $totShares = 0;
        foreach (($posts['data'] ?? []) as $p) {
            $likes = $p['reactions']['summary']['total_count'] ?? ($p['likes']['summary']['total_count'] ?? 0);
            $comments = $p['comments']['summary']['total_count'] ?? 0;
            $shares = $p['shares']['count'] ?? 0;
            $totLikes += $likes; $totComments += $comments; $totShares += $shares;

            $this->accounts->upsertPost('facebook_page', $p['id'], [
                'account_id' => $acc['id'],
                'post_type' => 'post',
                'caption' => $p['message'] ?? null,
                'permalink' => $p['permalink_url'] ?? null,
                'thumbnail' => $p['full_picture'] ?? null,
                'published_at' => !empty($p['created_time']) ? date('Y-m-d H:i:s', strtotime($p['created_time'])) : null,
                'likes' => $likes,
                'comments' => $comments,
                'shares' => $shares,
                'engagement' => $likes + $comments + $shares,
            ]);
        }

        $fbFollowers = $info['followers_count'] ?? ($info['fan_count'] ?? $acc['followers']);
        $this->accounts->saveMetrics($acc['id'], [
            'display_name' => $info['name'] ?? $acc['display_name'],
            'avatar' => $info['picture']['data']['url'] ?? $acc['avatar'],
            'followers' => $fbFollowers,
            'total_likes' => $totLikes,
            'total_comments' => $totComments,
            'total_shares' => $totShares,
        ]);
        $this->saveAccountSnapshot($acc, 'facebook', [
            'followers' => $fbFollowers,
            'likes' => $totLikes,
            'comments' => $totComments,
            'shares' => $totShares,
        ]);
        return 1;
    }

    private function syncLinkedin($acc, &$errors)
    {
        $api = $acc['access_token'] ? new LinkedInApi($acc['access_token']) : new LinkedInApi();
        if (!$api->hasToken()) { $errors[] = 'LinkedIn sem token'; return 0; }

        $followers = $api->getFollowerCount($acc['external_id']);
        $stats = $api->getOrganizationShareStats($acc['external_id']);
        $totals = LinkedInApi::shareTotals($stats);

        $likeCount = $totals['likeCount'] ?? null;
        $commentCount = $totals['commentCount'] ?? null;
        $shareCount = $totals['shareCount'] ?? null;
        $impressions = $totals['impressionCount'] ?? null;
        $engRate = isset($totals['engagement']) ? round($totals['engagement'] * 100, 2) : null;

        $this->accounts->saveMetrics($acc['id'], [
            'followers' => $followers,
            'impressions' => $impressions,
            'total_likes' => $likeCount,
            'total_comments' => $commentCount,
            'total_shares' => $shareCount,
            'engagement_rate' => $engRate,
            'extra_json' => json_encode($totals),
        ]);
        $this->saveAccountSnapshot($acc, 'linkedin', [
            'followers' => $followers,
            'impressions' => $impressions,
            'likes' => $likeCount,
            'comments' => $commentCount,
            'shares' => $shareCount,
            'engagement_rate' => $engRate,
            'extra_json' => json_encode($totals),
        ]);
        return 1;
    }
}
