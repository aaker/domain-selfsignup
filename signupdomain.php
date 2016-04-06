
<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />	<title>
	Domain Signup Page</title>
			<link rel="shortcut icon" type="image/x-icon" href="https://alpha1.netsapiens.com/SiPbx/getimage.php?filename=favicon.gif&server=corp.netsapiens.com" />
		<link rel="icon" type="image/x-icon" href="https://alpha1.netsapiens.com/SiPbx/getimage.php?filename=favicon.gif&server=corp.netsapiens.com" />
			
		<link rel="stylesheet" href="https://alpha1.netsapiens.com/portal/css/bootstrap.min.css" type="text/css">
		<link rel="stylesheet" href="https://alpha1.netsapiens.com/portal/css/jquery-ui-1.10.bootstrap.css" type="text/css">
	
		<link rel="stylesheet" href="https://alpha1.netsapiens.com/portal/css/portal.php?background=%23eeefe9&primary1=%237f223c&primary2=%23c0919e&bar1=%238c8c8c&bar2=%23cccccc" type="text/css">
						
		
</head>
<body>
	<div class="fixed-container">
			</div>
	<div id="login-container">
		
<div id="login-group">
	<div id="login-box" style="
    width: 350px;
">
		
		<div id="login-logo">
		
		
					
		<img src="https://alpha1.netsapiens.com/SiPbx/getimage.php?server=corp.netsapiens.com&filename=portal_landing.png&server=corp.netsapiens.com" />
		</div>
		
		<div id="login-text"  style="width: 330px;  font-size: 90%;text-align: center; padding-top: 10px;padding-left: 10px;padding-right: 10px;">
		
		
		Example creating a new domain... Please enter the data below.
		
		</div>
		
		<form action="adddomain.php" class="form-stacked" id="LoginLoginForm" method="post" accept-charset="utf-8"><div style="display:none;"><input type="hidden" name="_method" value="POST" /></div>		<div id="login-fields">
			
			<div>
				<label for="reseller">Reseller Name</label>
				<div>
					<input name="reseller" type="text" id="" />				</div>
			</div>
			<div>
				<label for="domain">Prefered Domain Name</label>
				<div>
					<input name="domain" type="text" id="" />				</div>
			</div>
			<div>
				<label for="email">Email</label>
				<div>
					<input  name="email"  type="text" id="" />				</div>
			</div>
			
		</div>
		<BR>
		<div id="login-submit">
				<div class="submit"><input class="btn color-primary" update="#login"  type="submit" value="Create Account" /></div>		</div>
		</form>		
	</div>
	<div id="footer">
					<p>Copyright &#169; 2015 by NetSapiens, Inc.</p>	
			<p>Domain Creation Script </p>
	
	</div><!-- /login-box -->
	<?php  if (isset($_REQUEST['error'])){
		
		if ($_REQUEST['error'] == "noResources")
			echo '<div class="alert alert-danger flashMessage login-message">We are currently unable to process do to lack of Available phone numbers. </div>';
		if ($_REQUEST['error'] == "domain")
			echo '<div class="alert alert-danger flashMessage login-message">The Domain already Exists. </div>';
		if ($_REQUEST['error'] == "reseller")
			echo '<div class="alert alert-danger flashMessage login-message">The Reseller doesn\'t exist. </div>';
		if ($_REQUEST['error'] == "incomplete")
			echo '<div class="alert alert-danger flashMessage login-message">Please fill out all of the fields. </div>';
		if ($_REQUEST['error'] == "server")
			echo '<div class="alert alert-danger flashMessage login-message">Sorry! Something went wrong on our side. Please try again later. </div>';
		
		
	} ?>
	
</div><!-- /login-group -->
	
	</div>	
	<!-- majority of javascript at bottom so the page displays faster -->
		
</body>

</html>