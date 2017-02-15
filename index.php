<?php
/**
 ***********************************************************************************************
 * Set the correct startpage for Admidio
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if(is_file('adm_my_files/config.php'))
{
    require_once(__DIR__ . '/adm_my_files/config.php');
    require_once(__DIR__ . '/adm_program/system/init_globals.php');
    require_once(__DIR__ . '/adm_program/system/constants.php');
    require_once(__DIR__ . '/adm_program/system/function.php');

    if(isset($gHomepage))
    {
        admRedirect($gHomepage);
        // => EXIT
    }
    else
    {
        // if parameter gHomepage doesn't exists then show default page
        admRedirect(ADMIDIO_URL . '/adm_program/index.php');
        // => EXIT
    }
}
else
{
    // config file doesn't exists then show installation wizard
    header('Location: adm_program/installation/index.php');
    exit();
}
