<?php
require_once("../header.php");
require_once("../log.php");
$dir = dirname(__FILE__);

set_time_limit(0);

// 读取待发货
if(isset($_GET['show_send_info'])){
	$id = $_GET['show_send_info'];
	$sql = "SELECT * FROM send_table WHERE id = '{$id}'";
	$res = $db->getAll($sql);

	echo json_encode($res);
}

// 查看库存数
if(isset($_GET['check_repo'])){
	$goods_code = $_GET['check_repo'];

	$sql = "SELECT b_repo FROM goods_type WHERE goods_code = '{$goods_code}'";
	$res = $rdb->getOne($sql);
	echo $res['b_repo'];
}

// 添加待发货item
if(isset($_POST['add_send_item'])){
	$today = date('y-m-d',time()); //获取日期
	$add_goods_code = trim(addslashes($_POST['add_send_item']));
	$add_goods_num = $_POST['add_goods_num'];
	$add_item_price = $_POST['add_item_price'];
	$add_yfcode = $_POST['add_yfcode'];
	$add_cod_money = $_POST['add_cod_money'];
	$id = $_POST['id']; 	//复制该条信息的ID

	// 查询基础信息
	$sql = "SELECT * FROM send_table WHERE id = '{$id}'";
	$res = $db->getOne($sql);
	$who_email = $res['who_email'];
	$who_tel = $res['who_tel'];
	$who_name = $res['who_name'];
	$who_post = $res['who_post'];
	$who_house = $res['who_house'];
	$is_cod = $res['is_cod'];
	$send_method = $res['send_method'];
	$station = $res['station'];
	$store = $res['store_name'];
	$oms_id = $res['oms_id'];
	$send_id = $res['send_id'];
	$response_list = $station.'_response_list';
	$response_info = $station.'_response_info';

	//运费金额计算 ###############
	$add_yf_money = '0';	//暂时为0

	// 金额计算
	if($is_cod == 'COD'){
		$due_money = $add_item_price + $add_cod_money + $add_yf_money;
	}else{
		$due_money = 0;
		$add_cod_money = 0;
	}

	// 通过 oms_id 查询订单号
	$sql = "SELECT order_id FROM $response_list WHERE id = '{$oms_id}'";
	$res = $db->getOne($sql);
	$order_id = $res['order_id'];

	// 插入 resoponse_info
	$sql = "INSERT INTO $response_info (
		store,
		order_id,
		holder,
		goods_title,
		sku_ok,
		yfcode_ok,
		yfcode,
		yf_money,
		sku,
		goods_code,
		goods_num,
		item_price,
		cod_money) VALUES(
		'{$store}',
		'{$order_id}',
		'{$u_name}',
		concat('{$u_name}','添加'),
		'1',
		'1',
		'{$add_yfcode}',
		'{$add_yf_money}',
		'{$add_goods_code}',
		'{$add_goods_code}',
		'{$add_goods_num}',
		'{$add_item_price}',
		'{$add_cod_money}'
		) ";
	$res = $db->execute($sql);

	// 查询 info_id
	$sql = "SELECT max(id) as info_id FROM $response_info WHERE order_id = '{$order_id}'";
	$res = $db->getOne($sql);
	$info_id = $res['info_id'];

	// 扣库存
	$sql = "UPDATE goods_type SET b_repo = b_repo - $add_goods_num WHERE goods_code = '{$add_goods_code}'";
	$res = $rdb->execute($sql);

	// 扣掉库存后记录库存系统流水
	$sql = "INSERT INTO jp_list_out (order_id,goods_code,out_num,oms_id,store_name,holder,out_day,import_day) VALUES ('{$order_id}','{$add_goods_code}','{$add_goods_num}','{$send_id}','{$store}','{$u_name}','{$today}','{$today}')";
	$res = $rdb->execute($sql);

	// 插入send_table
	$sql = "INSERT INTO send_table (
		station,
		send_id,	#合单发货ID
		oms_id,	#OMS-ID
		info_id, #info-ID
		sku, 	#sku，客人看
		goods_code,	#商品代码，仓库看
		out_num,	#商品数量
		who_tel,	#配送电话
		who_post,	#邮编
		who_house,	#地址
		who_name,	#收货人
		is_cod,		#是否代引
		due_money,	#代引金额，写出全部的item金额，根据cod，更新是否是代引
		send_method,
		who_email,	#邮编
		store_name,	#店铺名
		holder,		#担当者
		import_day) VALUES (
		'{$station}',
		'{$send_id}',
		'{$oms_id}',
		'{$info_id}',
		'{$add_goods_code}',
		'{$add_goods_code}',
		'{$add_goods_num}',
		'{$who_tel}',
		'{$who_post}',
		'{$who_house}',
		'{$who_name}',
		'{$is_cod}',
		'{$due_money}',
		'{$send_method}',
		'{$who_email}',
		'{$store}',
		'{$u_name}',
		'{$today}')";
	$res = $db->execute($sql);

	// 如果COD_money大于0，则为代引
	if($add_cod_money > 0){
		//日志
		$do = ' [新增一单]：订单号【'.$order_id.'】商品代码【'.$add_goods_code.'】数量【'.$add_goods_num.'】子订单价格【'.$add_item_price.'】运费代码【'.$add_yfcode.'】运费金额【'.$add_yf_money.'】代引金额【'.$add_cod_money.'】';

	}else{
		//日志
		$do = ' [新增一单]：订单号【'.$order_id.'】商品代码【'.$add_goods_code.'】数量【'.$add_goods_num.'】子订单价格【'.$add_item_price.'】运费代码【'.$add_yfcode.'】运费金额【'.$add_yf_money.'】';
	}

	oms_log($u_name,$do,'ready_send',$station,$store,$oms_id);
	$final_res['status'] = 'ok';
	$final_res['order_id'] = $order_id;
	$final_res['station'] = $station;
	$final_res['store'] = $store;
	echo json_encode($final_res);
}

// 删除待发货item
if(isset($_GET['del_send_item'])){
	$id = $_GET['del_send_item'];
	$oms_id = $_GET['oms_id'];
	$info_id = $_GET['info_id'];
	$store = $_GET['store'];
	$station = strtolower($_GET['station']);
	$response_list = $station.'_response_list';
	$response_info = $station.'_response_info';

	// 查 out_num 和 goods_code
	$sql = "SELECT goods_code,out_num FROM send_table WHERE id = '{$id}'";
	$res = $db->getOne($sql);
	$goods_code = $res['goods_code'];
	$out_num = $res['out_num'];

	// 还库存
	$sql = "UPDATE goods_type SET b_repo = b_repo + $out_num WHERE goods_code = '{$goods_code}'";
	$res = $rdb->execute($sql);

	// 删除 send_table
	$sql = "DELETE FROM send_table WHERE id = '{$id}'";
	$res = $db->execute($sql);

	// 查询删除的item
	$sql = "SELECT * FROM $response_info WHERE id = '{$info_id}'";
	$res = $db->getOne($sql);

	// 删除 info_table
	$sql = "DELETE FROM $response_info WHERE id = '{$info_id}'";
	$res1 = $db->execute($sql);

	$do = '[删除一单]：订单号【'.$res['order_id'].'】商品代码【'.$res['goods_code'].'】数量【'.$res['goods_num'].'】子订单价格【'.$res['item_price'].'】运费代码【'.$res['yfcode'].'】运费金额【'.$res['yf_money'].'】代引金额【'.$res['cod_money'].'】';

	oms_log($u_name,$do,'ready_send',$station,$store,$oms_id);

	$final_res['status'] = 'ok';
	$final_res['order_id'] = $res['order_id'];
	echo json_encode($final_res);
}

// 验证send字段
if(isset($_GET['need_check_send'])){
	$field_name = $_GET['field_name'];
	$new_key = $_GET['new_key'];

	//	收件人/电话/地址
	if($field_name == 'who_name' or $field_name == 'who_tel' or $field_name == 'who_email'){
		echo 'ok';
	}

	// 数量 or 代引金额
	if($field_name == 'out_num' or $field_name == 'due_money'){
		if($new_key < 0){
			echo '不能为负数。';
		}else{
			echo 'ok';
		}
	}

	// goods_code
	if($field_name == 'goods_code'){
		$sql = "SELECT 1 FROM goods_type WHERE goods_code='{$new_key}' limit 1";
	    $res = $rdb->getOne($sql);
	    if(empty($res)){
	    	echo '无此商品代码。';
	    }else{
	    	echo 'ok';
	    }
	}

}

//修改send字段
if(isset($_GET['change_send_field'])){
	$store = $_GET['store'];
    $station = strtolower($_GET['station']);
	$id = $_GET['id'];	//send_table 的 ID
	$oms_id = $_GET['oms_id'];
	$info_id = $_GET['info_id'];
	$o_key = $_GET['o_key'];
	$field_name = $_GET['field_name'];
	$new_key = addslashes($_GET['new_key']);

	$response_list = $station.'_response_list';
	$response_info = $station.'_response_info';

	if($field_name == 'who_tel'){
		$ch_field = '电话';
		$list_field = 'phone';
		// 更新LIST
		$sql = "UPDATE $response_list SET $list_field = '{$new_key}' WHERE id = '{$oms_id}'";
		$res = $db->execute($sql);
		// 更新 send_table
		$sql = "UPDATE send_table SET $field_name = '{$new_key}' WHERE id = '{$id}'";
		$res = $db->execute($sql);
	}
	if($field_name == 'who_name'){
		$ch_field = '收件人';
		$list_field = 'receive_name';
		// 更新LIST
		$sql = "UPDATE $response_list SET $list_field = '{$new_key}' WHERE id = '{$oms_id}'";
		$res = $db->execute($sql);
		// 更新 send_table
		$sql = "UPDATE send_table SET $field_name = '{$new_key}' WHERE id = '{$id}'";
		$res = $db->execute($sql);
	}
	if($field_name == 'who_email'){
		$ch_field = '邮箱';
		$list_field = 'buyer_email';
		// 更新LIST	
		$sql = "UPDATE $response_list SET $list_field = '{$new_key}' WHERE id = '{$oms_id}'";
		$res = $db->execute($sql);
		// 更新 send_table
		$sql = "UPDATE send_table SET $field_name = '{$new_key}' WHERE id = '{$id}'";
		$res = $db->execute($sql);
	}
	if($field_name == 'goods_code'){
		$ch_field = '商品代码';
		$list_field = 'goods_code';
		// 更新INFO
		$sql = "UPDATE $response_info SET $list_field = '{$new_key}' WHERE id = '{$info_id}'";
		$res = $db->execute($sql);
		// 更新 send_table
		$sql = "UPDATE send_table SET $field_name = '{$new_key}' WHERE id = '{$id}'";
		$res = $db->execute($sql);
	}
	if($field_name == 'out_num'){
		$ch_field = '数量';
		$list_field = 'goods_num';
		// 更新INFO
		$sql = "UPDATE $response_info SET $list_field = '{$new_key}' WHERE id = '{$info_id}'";
		$res = $db->execute($sql);
		// 更新 send_table
		$sql = "UPDATE send_table SET $field_name = '{$new_key}' WHERE id = '{$id}'";
		$res = $db->execute($sql);
	}
	if($field_name == 'due_money'){
		$ch_field = '代引金额';
		$list_field = 'pay_money';
		// 更新LIST
		// $sql = "UPDATE $response_list SET $list_field = '{$new_key}' WHERE id = '{$oms_id}'";
		// $res = $db->execute($sql);
		// 更新 send_table
		$sql = "UPDATE send_table SET $field_name = '{$new_key}' WHERE id = '{$id}'";
		$res = $db->execute($sql);
	}

	// 日志
	$do = '修改 <'.$ch_field.'>【'.$o_key.'】为【'.$new_key.'】';
	oms_log($u_name,$do,'ready_send',$station,$store,$oms_id);

	echo 'ok';
}

// 邮编、地址验证。通过则更新。
if(isset($_GET['change_post_addr'])){
	$store = $_GET['store'];
    $station = strtolower($_GET['station']);
	$id = $_GET['id'];	//send_table 的 ID
	$oms_id = $_GET['oms_id'];
	$info_id = $_GET['info_id'];
	$response_list = $station.'_response_list';
	$new_post_code = addslashes($_GET['new_post_code']);
	$new_address = addslashes($_GET['new_address']);

	// 查询出该 post_code 的对应市区
	$sql = "SELECT post_name FROM oms_post WHERE post_code = '{$new_post_code}'";
	$res = $db->getOne($sql);
	$post_name = $res['post_name'];
	if($post_name == ''){
		echo '邮编不存在。';
	}else{
		// 邮编和地址匹配
		if(strstr($new_address, $post_name) == true){
			// 查询原字段值
			$sql = "SELECT post_code,address FROM $response_list WHERE id = '{$oms_id}'";
			$res = $db->getOne($sql);
			$o_post_code = $res['post_code'];
			$o_address = $res['address'];

			// 保存 LIST
			$sql = "UPDATE $response_list SET post_code = '{$new_post_code}',address = '{$new_address}' WHERE id = '{$oms_id}'";
		    $res = $db->execute($sql);

		    // 保存 send_table
			$sql = "UPDATE send_table SET who_post = '{$new_post_code}',who_house = '{$new_address}' WHERE id = '{$id}'";
		    $res = $db->execute($sql);

		    // 日志
			$do = 'OMS-ID【'.$oms_id.'】修改 <邮编/地址>【'.$o_post_code.'/'.$o_address.'】为【'.$new_post_code.'/'.$new_address.'】';
			oms_log($u_name,$do,'ready_send',$station,$store,$oms_id);

			echo 'ok';
		}else{
			echo '邮编、地址不匹配。';
		}
	}
}