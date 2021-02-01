<?php

/*
        - called from edu-sharing after selecting a node/resource in the opened popup window
        - transfers the node-id into the Location field of the opener (edit resource window)
        - closes popup
*/


//todo check values
//get width and height from repo
$id    = $_GET['nodeId'];
$title = utf8_encode($_GET['title']);
$mimetype = utf8_encode($_GET['mimeType']);
$repotype = utf8_encode($_GET['repoType']);
$width = (isset($_GET['w']))?$_GET['w']:'400';
$height = (isset($_GET['h']))?$_GET['h']:'300';
$version = (isset($_GET['v']))?$_GET['v']:'1.0';


echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
        <title></title>
        <meta http-equiv="Content-Type"        content="text/html; charset=utf-8" />
        <meta http-equiv="Content-Script-Type" content="text/javascript">
        <meta http-equiv="Content-Style-Type"  content="text/css">
        <meta http-equiv="expires"             content="86400">
        <script type="text/javascript">

if (opener)
{
        opener.setData("'.$id.'","'.utf8_decode($title).'","'.$mimetype.'","'.$width.'","'.$height.'","'.$version.'","'.$repotype.'");
        parent.hideEduFrame();
}
else if (parent)
{
        parent.setData("'.$id.'","'.utf8_decode($title).'","'.$mimetype.'","'.$width.'","'.$height.'","'.$version.'","'.$repotype.'");
        parent.hideEduFrame();
}
else
{
        alert("neither opener nor parent frame could be found.");
}

        </script>
</head>
<body>
</body>
</html>';
