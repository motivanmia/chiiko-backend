  <?php
    require_once __DIR__ . '/../../common/config.php';
    require_once __DIR__ . '/../../common/conn.php';
    require_once __DIR__ . '/../../common/cors.php';

    

    $method = $_SERVER['REQUEST_METHOD'];


    if($method === 'GET'){
      $user_id = $_SESSION['user_id'] ?? 0;
      // echo json_encode($user_id);
      // exit;
      if($user_id<=0){
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'缺少有效會員ID']);
        exit;
      }
      try{
        $escaped_user_id = $mysqli->real_escape_string($user_id);
        
        //連結recipe_favorite及recipe
        $query =
          "SELECT
            r.*
          FROM
            `recipe_favorite` AS rf
          JOIN
            `recipe` AS r ON rf.recipe_id = r.recipe_id
          WHERE
            rf.member_id = '$escaped_user_id'";

          $result = $mysqli->query($query);

          if(!$result){
            throw new \mysqli_sql_exception('查詢失敗:'.$mysqli->error);
          }

          $favoriteRecipes = $result->fetch_all(MYSQLI_ASSOC);

          foreach ($favoriteRecipes as &$recipe){
            $recipe['image'] = IMG_BASE_URL . '/' . $recipe['image'];
          }

          header('Content-Type:application/json');
          echo json_encode(['success'=>true,'data'=>$favoriteRecipes]);
      }catch(\mysqli_sql_exception $e){
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'查詢失敗:'.$e->getMessage()]);
      }
    }else{
        http_response_code(405);
        echo json_encode(['success'=>false,'error'=>'Method Not Allowed']);
      }
    
  ?>