<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php $view['blocks']->output('pageTitle', 'Mautic'); ?></title>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css" type="text/css" />
        <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
        <?php $view['blocks']->outputHeadDeclarations(); ?>
    </head>
    <body>
        <?php $view['blocks']->outputScripts("bodyOpen"); ?>
        <?php $view['blocks']->output('_content'); ?>
        <?php $view['blocks']->outputScripts("bodyClose"); ?>
    </body>
</html>