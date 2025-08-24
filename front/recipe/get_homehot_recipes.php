<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/../../common/config.php';

global $mysqli;

if ($_SERVER['REQUEST_METHOD'] !== 'GET'){send_json(["status"=>"error","message"=>"不允許的請求方法"],405);
}

try {
  $hotRecipes=[];

  $hotRecipeQuery ="SELECT recipe_id,name,image
                    FROM recipe
                    ORDER BY views DESC
                    LIMIT 4;";
  $hotRecipeResult=$mysqli->query($hotRecipeQuery);
  if(!$hotRecipeResult){
    throw new \mysqli_sql_exception('查詢失敗:'.$mysqli->error);
  }
  $hotRecipes =$hotRecipeResult->fetch_all(MYSQLI_ASSOC);

  foreach($hotRecipes as &$recipe){
      $recipe['image'] =IMG_BASE_URL.'/'.$recipe['image'];
    }
  send_json(["status"=>"success","data"=>$hotRecipes],200);
}catch(\mysqli_sql_exception$e){
  send_json(["status"=>"error","message"=>"資料庫操作失敗:".$e->getMessage()], 500);
}finally{
  if(isset($mysqli)){
    $mysqli->close();
  }
}
?>