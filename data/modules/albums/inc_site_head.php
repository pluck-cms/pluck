<?php
/*
 * This file is part of pluck, the easy content management system
 * Copyright (c) somp (www.somp.nl)
 * http://www.pluck-cms.org
 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * See docs/COPYING for the complete license.
*/

//Only insert LightBox when we're viewing an album
if (isset($_GET['module'])) {
	$module = $_GET['module'];
}
if (isset($module) && ($module == 'albums')) {
echo "<link href=\"data/inc/lightbox/lightbox.css\" rel=\"stylesheet\" type=\"text/css\" media=\"screen\" />
<script src=\"data/inc/lightbox/prototype.js\" type=\"text/javascript\"></script>
<script src=\"data/inc/lightbox/scriptaculous.js?load=effects\" type=\"text/javascript\"></script>
<script src=\"data/inc/lightbox/lightbox.js\" type=\"text/javascript\"></script>";
}
?>