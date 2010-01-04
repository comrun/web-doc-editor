<?php

require_once dirname(__FILE__) . '/AccountManager.php';
require_once dirname(__FILE__) . '/DBConnection.php';
require_once dirname(__FILE__) . '/RepositoryManager.php';

class TranslationStatistic
{
    private static $instance;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Get number/size of all files.
     *
     * @param $lang Can be either 'all' for all availables languages, or one specific language
     * @return An associated array
     */
    public function getFileCount($lang='all')
    {
        $project = AccountManager::getInstance()->project;

        if( $lang == 'all' ) {
            $where = '';
            $groupBy = 'GROUP BY `lang`';
        } else {
            $where = '`lang` = \''.$lang.'\' AND';
            $groupBy = '';
        }

        $s = 'SELECT
                    COUNT(*) AS total,
                    SUM(`size`) AS total_size,
                    `lang`
                FROM
                    `files`
                WHERE
                    ' . $where . '
                    ( `status` != "NotInEN" OR `status` IS NULL ) AND
                    `project` = \''.$project.'\'
                ' . $groupBy . '
        ';
        $res = DBConnection::getInstance()->query($s);

        while( $r = $res->fetch_array() ) {
            $result[$r['lang']]['total']      = $r['total'];
            $result[$r['lang']]['total_size'] = $r['total_size'];
        }
        return $result;
    }

    /**
     * Get number of translated files.
     *
     * @param $lang Can be either 'all' for all availables languages, or one specific language
     * @return An associated array
     */
    public function getTransFileCount($lang='all')
    {
        $project = AccountManager::getInstance()->project;

        if( $lang == 'all' ) {
            $where = '';
            $groupBy = 'GROUP BY `lang`';
        } else {
            $where = '`lang` = \''.$lang.'\' AND';
            $groupBy = '';
        }

        $s = 'SELECT
                COUNT(`name`) AS total,
                SUM(`size`)   AS total_size,
                `lang`
            FROM
                files
            WHERE
                ' . $where . '
                `revision` = `en_revision` AND
                `revision` != 0 AND
                `project` = \''.$project.'\'
            ' . $groupBy . '
        ';
        $res = DBConnection::getInstance()->query($s);

        while( $r = $res->fetch_array() ) {
            $result[$r['lang']]['total']      = $r['total'];
            $result[$r['lang']]['total_size'] = $r['total_size'];
        }
        return $result;
    }

    /**
     * Get statistic about stales files which need to be updated.
     *
     * @param $lang Can be either 'all' for all availables languages, or one specific language
     * @return An associated array
     */
    public function getStaleFileCount($lang='all')
    {
        $project = AccountManager::getInstance()->project;

        if( $lang == 'all' ) {
            $where = '';
            $groupBy = 'GROUP BY `lang`';
        } else {
            $where = '`lang` = \''.$lang.'\' AND';
            $groupBy = '';
        }

        $s = 'SELECT
                COUNT(`name`) AS total,
                SUM(`size`) AS total_size,
                `lang`
            FROM
                `files`
            WHERE
                ' . $where . '
                (`en_revision` != `revision` OR `en_revision` = 0 )
            AND
                `size` is not NULL
            AND
                `project` = \''.$project.'\'
            ' . $groupBy . '
        ';
        $res = DBCOnnection::getInstance()->query($s);

        while( $r = $res->fetch_array() ) {
            $result[$r['lang']]['total']      = $r['total'];
            $result[$r['lang']]['total_size'] = $r['total_size'];
        }
        return $result;
    }

    /**
     * Get statistic about files which need to be translated.
     *
     * @param $lang Can be either 'all' for all availables languages, or one specific language
     * @return An associated array
     */
    public function getNoTransFileCount($lang='all')
    {
        $project = AccountManager::getInstance()->project;

        if( $lang == 'all' ) {
            $where = '`lang` != \'en\' AND';
        } else {
            $where = '`lang` = \''.$lang.'\' AND';
        }

        // We get EN files

       $s = 'SELECT * FROM files WHERE `lang`=\'en\' AND `project`=\''.$project.'\'';

       $r = DBConnection::getInstance()->query($s);

       while( $a = $r->fetch_object() ) {
          $resultEN[$a->path.$a->name] = $a->size;
       }

       $s = 'SELECT
                 `path`, `name`, `lang`
             FROM
                 `files`
             WHERE
                 ' . $where . '
                 `revision` is NULL AND
                 `size` is NULL AND
                 `project` = \''.$project.'\' AND
                 ( `status` != "NotInEN" OR `status` IS NULL )
       ';

       $r = DBConnection::getInstance()->query($s);

       while( $a = $r->fetch_object() ) {
          $result[$a->lang][$a->path.$a->name] = 'exist';
       }

       while( list($a, $b) = each($result) ) {
           $size[$a] = 0;
           while( list($k, $v) = each($result[$a]) ) {
               $size[$a] += $resultEN[$k];
           }
       }

       //
       reset($result);
       while( list($a, $b) = each($result) ) {
           $summary[$a]['total'] = count($result[$a]);
           $summary[$a]['total_size'] = $size[$a];
       }
       return $summary;
    }

    /**
     * Compute summary of translation statistic and store it into DB
     *
     * @param $lang Can be either 'all' for all availables languages, or one specific language
     */
    public function computeSummary($lang='all')
    {

        $nbFiles   = $this->getFileCount($lang);
        $uptodate  = $this->getTransFileCount($lang);
        $stale     = $this->getStaleFileCount($lang);
        $missFiles = $this->getNoTransFileCount($lang);

        if( $lang == 'all' ) {
            $hereLang = RepositoryManager::getInstance()->getExistingLanguage();
        } else {
            $hereLang = array(0 => Array("code" => $lang));
        }

        foreach( $hereLang as $lang ) {

            $lang = $lang["code"];

            $summary = array();

            $summary[0]['id']            = 1;
            $summary[0]['libel']         = 'Up to date files';
            $summary[0]['nbFiles']       = ( isset($uptodate[$lang]['total']) )       ? $uptodate[$lang]['total'] : 0;
            $summary[0]['percentFiles']  = ( isset($uptodate[$lang]['total']) )       ? round(($uptodate[$lang]['total']*100)/$nbFiles[$lang]['total'], 2) : 0;
            $summary[0]['sizeFiles']     = ( !isset($uptodate[$lang]['total_size']) ) ? 0 : $uptodate[$lang]['total_size'];
            $summary[0]['percentSize']   = (!isset($uptodate[$lang]['total_size']))   ? 0 : round(($uptodate[$lang]['total_size']*100)/$nbFiles[$lang]['total_size'], 2);

            $summary[1]['id']            = 2;
            $summary[1]['libel']         = 'Stale files';
            $summary[1]['nbFiles']       = ( isset($stale[$lang]['total']) ) ? $stale[$lang]['total'] : 0;
            $summary[1]['percentFiles']  = round(($stale[$lang]['total']*100)/$nbFiles[$lang]['total'], 2);
            $summary[1]['sizeFiles']     = ($stale[$lang]['total_size'] == '' ) ? 0 : $stale[$lang]['total_size'];
            $summary[1]['percentSize']   = (!isset($stale[$lang]['total_size']) || $stale[$lang]['total_size'] == 0 ) ? 0 : round(($stale[$lang]['total_size']*100)/$nbFiles[$lang]['total_size'], 2);

            $summary[2]['id']            = 3;
            $summary[2]['libel']         = 'Files available for translation';
            $summary[2]['nbFiles']       = ( isset($missFiles[$lang]['total']) ) ? $missFiles[$lang]['total'] : 0;
            $summary[2]['percentFiles']  = round(($missFiles[$lang]['total']*100)/$nbFiles[$lang]['total'], 2);
            $summary[2]['sizeFiles']     = ($missFiles[$lang]['total_size'] == '' ) ? 0 : $missFiles[$lang]['total_size'];
            $summary[2]['percentSize']   = (!isset($missFiles[$lang]['total_size']) || $missFiles[$lang]['total_size'] == 0 || $nbFiles[$lang]['total_size'] == 0 ) ? 0 : round(($missFiles[$lang]['total_size']*100)/$nbFiles[$lang]['total_size'], 2);

            $summary[3]['id']            = 4;
            $summary[3]['libel']         = 'Total';
            $summary[3]['nbFiles']       = ( isset($nbFiles[$lang]['total']) ) ? $nbFiles[$lang]['total'] : 0;
            $summary[3]['percentFiles']  = '100%';
            $summary[3]['sizeFiles']     = ( isset($nbFiles[$lang]['total_size']) ) ? $nbFiles[$lang]['total_size'] : 0;
            $summary[3]['percentSize']   = '100%';

            // Save $summary into DB
            RepositoryManager::getInstance()->setStaticValue('translation_summary', $lang, json_encode($summary));
        }
    }

}

?>
