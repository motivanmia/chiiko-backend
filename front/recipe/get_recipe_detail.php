<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(["status" => "error", "message" => "不允許的請求方法"], 405);
}

if (!isset($_GET['recipe_id'])) {
    send_json(["status" => "error", "message" => "缺少食譜ID"], 400);
}

$recipe_id = $_GET['recipe_id'];

if (!is_numeric($recipe_id) || $recipe_id <= 0) {
    send_json(["status" => "error", "message" => "無效的食譜ID"], 400);
}

$safe_recipe_id = (int)$recipe_id;

try {
    // 步驟 1: 取得食譜基本資訊，包含收藏數
    $sql_recipe = "
        SELECT 
            r.*,
            COUNT(rf.recipe_id) AS favorites_count,
            COALESCE(u.name, m.name) AS author_name,
            COALESCE(u.image, NULL) AS author_image
        FROM recipe r
        LEFT JOIN users u ON r.user_id = u.user_id
        LEFT JOIN managers m ON r.manager_id = m.manager_id
        LEFT JOIN recipe_favorite rf ON r.recipe_id = rf.recipe_id
        WHERE r.recipe_id = $safe_recipe_id
        GROUP BY r.recipe_id
    ";
    
    $result_recipe = mysqli_query($mysqli, $sql_recipe);
    
    if (!$result_recipe) {
        throw new Exception("資料庫查詢失敗: " . mysqli_error($mysqli));
    }

    if (mysqli_num_rows($result_recipe) === 0) {
        send_json(["status" => "error", "message" => "找不到指定的食譜", "data" => null], 404);
    }
    
    $recipe = mysqli_fetch_assoc($result_recipe);
    mysqli_free_result($result_recipe);

    // 步驟 2: 取得食譜食材
    $sql_ingredients = "
        SELECT 
            ii.ingredient_item_id,
            ii.serving,
            i.name
        FROM ingredient_item ii
        LEFT JOIN ingredients i ON ii.ingredient_id = i.ingredient_id
        WHERE ii.recipe_id = $safe_recipe_id
    ";
    $result_ingredients = mysqli_query($mysqli, $sql_ingredients);
    
    $ingredients = [];
    if ($result_ingredients) {
        while ($row = mysqli_fetch_assoc($result_ingredients)) {
            $ingredients[] = [
                'ingredient_item_id' => $row['ingredient_item_id'],
                'name' => $row['name'],
                'amount' => $row['serving']
            ];
        }
        mysqli_free_result($result_ingredients);
    }

    // 步驟 3: 取得食譜步驟
    $sql_steps = "SELECT step_id, `order`, content FROM steps WHERE recipe_id = $safe_recipe_id ORDER BY `order` ASC";
    $result_steps = mysqli_query($mysqli, $sql_steps);
    
    $steps = [];
    if ($result_steps) {
        while ($row = mysqli_fetch_assoc($result_steps)) {
            $steps[] = $row;
        }
        mysqli_free_result($result_steps);
    }

    // 步驟 4: 取得食譜留言 (主留言)
    $sql_comments = "
        SELECT
            c.*,
            COALESCE(u.name, m.name) AS author_name,
            COALESCE(u.image, NULL) AS author_image
        FROM recipe_comment c
        LEFT JOIN users u ON c.member_id = u.user_id
        LEFT JOIN managers m ON c.member_id = m.manager_id
        WHERE c.recipe_id = $safe_recipe_id AND c.parent_id IS NULL
        ORDER BY c.created_at ASC
    ";
    $result_comments = mysqli_query($mysqli, $sql_comments);
    
    $comments = [];
    if ($result_comments) {
        while ($row = mysqli_fetch_assoc($result_comments)) {
            $comments[] = $row;
        }
        mysqli_free_result($result_comments);
    }

    // 步驟 5: 取得每個主留言的回覆
    foreach ($comments as &$comment) {
        $comment_id = (int)$comment['comment_id'];
        $sql_replies = "
            SELECT
                c.*,
                COALESCE(u.name, m.name) AS author_name,
                COALESCE(u.image, NULL) AS author_image
            FROM recipe_comment c
            LEFT JOIN users u ON c.member_id = u.user_id
            LEFT JOIN managers m ON c.member_id = m.manager_id
            WHERE c.parent_id = $comment_id
            ORDER BY c.created_at ASC
        ";
        $result_replies = mysqli_query($mysqli, $sql_replies);
        
        $replies = [];
        if ($result_replies) {
            while ($row_reply = mysqli_fetch_assoc($result_replies)) {
                $replies[] = $row_reply;
            }
            mysqli_free_result($result_replies);
        }
        $comment['replies'] = $replies;
    }
    unset($comment);

    // 步驟 6: 組裝最終的 JSON 資料
    $recipe_detail = [
        "recipe_id" => $recipe['recipe_id'],
        "name" => $recipe['name'],
        "content" => $recipe['content'],
        "image" => $recipe['image'],
        "author_name" => $recipe['author_name'],
        "author_image" => isset($recipe['author_image']) ? $recipe['author_image'] : null,
        "cooked_time" => $recipe['cooked_time'],
        "serving" => $recipe['serving'],
        "tag" => $recipe['tag'],
        "status" => $recipe['status'],
        "favorites_count" => (int)$recipe['favorites_count'],
        "comments" => $comments,
        "ingredients" => $ingredients,
        "steps" => $steps,
    ];

    // 步驟 7: 回傳成功的回應
    send_json(["status" => "success", "message" => "成功取得食譜資料", "data" => $recipe_detail]);

} catch (Exception $e) {
    send_json(["status" => "error", "message" => "處理請求時發生錯誤: " . $e->getMessage()], 500);
}
?>