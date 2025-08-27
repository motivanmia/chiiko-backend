  <?php
    require_once __DIR__ . '/../../common/config.php';
    require_once __DIR__ . '/../../common/conn.php';
    require_once __DIR__ . '/../../common/cors.php';

    

    $method = $_SERVER['REQUEST_METHOD'];


    if($method === 'DELETE'){
      $user_id = $_SESSION['user_id'] ?? 0;

      $data = json_decode(file_get_contents("php://input"),true);
      $recipe_id = $data['recipe_id'] ?? 0;

      if($user_id<=0){
        http_response_code(401);
        echo json_encode(['success'=>false,'error'=>'未經授權的操作']);
        exit;
      }

      if ($recipe_id<=0){
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'缺少有效食譜id']);
      }
      try{
        $escaped_user_id = $mysqli->real_escape_string($user_id);
        $escaped_recipe_id = $mysqli->real_escape_string($recipe_id);

        $query =
          "DELETE
            FROM `recipe_favorite`
            WHERE `user_id` = '$escaped_user_id' 
            AND `recipe_id` = '$escaped_recipe_id'
          ";

          $result = $mysqli->query($query);

          if(!$result){
            throw new \mysqli_sql_exception('刪除失敗:'.$mysqli->error);
          }

          header('Content-Type:application/json');
          echo json_encode(['success'=>true,'message'=>'刪除成功']);
      }catch(\mysqli_sql_exception $e){
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'刪除失敗:'.$e->getMessage()]);
      }
    }else{
        http_response_code(405);
        echo json_encode(['success'=>false,'error'=>'Method Not Allowed']);
      }
    
  ?>