<?php

namespace Low\YearlyArchives\FluxCapacitor\Conduit;

use Low\YearlyArchives\FluxCapacitor\FluxCapacitor;
use Low\YearlyArchives\FluxCapacitor\Conduit\StaticCache;

/**
 * Version class
 *
 * @package         Version
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */
class Version
{
    // Assume the license table is corrupt until we can verify otherwise.
    public $flux;
    private $table_corrupt = true;
    private $public_key = 'LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUlJQ0lqQU5CZ2txaGtpRzl3MEJBUUVGQUFPQ0FnOEFNSUlDQ2dLQ0FnRUF6UXZFMWpqdU5wZXVZUjhYY2dYbQpqamRVZ2NlT1NyY0xZRmJKNTA2TGNNQkFsMTBxV3E2aU5NRVJNbXAzNno2SWhvVG4xdVNYS2llY0NUd21ZUE9hCjZVaXZIaFJkYVlLdVMzMEN1U0wzTzJ4aVFBditrdW5aQkk3c1lsL0R5V1ljdVV1ckswdFlDSm1jUjQxd0dwU2oKYTVGblZja2hndE12d0ZWTGZXWGZ6ck5MTjdJZzJSVFdCNWlnSCtEaGlQVE5jNGRWR1FXSjI4RHBhMDBreFRjNAp3SHNGUGFHWDBndER3VzZ0ZUNnWFFzbkJmdVVSNE85bkMyVERINUZyakFrenNRa01nNDFBQnFyVFhVdWVaYkxnCjNkUUxUWm52eWxQYmRoamdEbCs5SzF3amt2dDl2Wlh1SFVaeTNneG1tYy8zaHU3M0w0RTVWRkdmRWxLZ0hiNngKbVBoajZVSlM1dWZuZ1VPcXdxNnhKZTd5NlhWTnJrem9ubWVQd2VSeHFsS3QxMzN1QXg5S3BRTEJOdWc1QmlvRQpjSHl2ZUFBZDVXOURKbStSS0ZaaG1XODdlcFBkczRpL0FDMnl2UGMxNEgwWGVOL2JuUDV2UlRDcitzaFNzWVN1CkxwTUsvRVNSRDJiOUxxNzhPVElaNG5WTXpzYk5xM3NKS1pwUUFsQnExTEIxSmlrSHRUdjNPK3pjbHpZeWNQbFYKaGRGRFk2QlBkMTNBYjdaQzZYOE5jTWpVN2V3L3pFMC95ME9tVHF2M044N0xTMXo0TmhoWjVySVBlbTd0cEVTQgpuaGgwTzUzcUhSR0JWV0U0eHZnV1VuNTgyK21RS0FuZW9COHVuSGFnQmI3M2VxRTFPMTErOUpSSWp2c1dzd21WCmdLbkJnVXZsdjlTS08xNzIvSVZrT2hNQ0F3RUFBUT09Ci0tLS0tRU5EIFBVQkxJQyBLRVktLS0tLQo=';

    public function __construct()
    {
        $this->flux = new FluxCapacitor;
        $this->verifyLicenseTable();
    }

    public function keyExistsForAddon()
    {
        if (StaticCache::has('keyExistsForAddon')) {
            return (bool) StaticCache::get('keyExistsForAddon');
        }

        // Find out if there's an existing license for this add-on on this site.
        ee()->db->where('site_id', ee()->config->item('site_id'));
        ee()->db->where('addon', 'Low\YearlyArchives');
        $count = ee()->db->count_all_results('eeharbor_licenses');

        StaticCache::put('keyExistsForAddon', $count);

        // Returns true or false if it exists
        return ($count > 0);
    }

    /**
     * Save the license and invalidate cached existence after a successful write.
     *
     * @param array $data License row values.
     * @param string $addon Add-on namespace.
     * @return void
     */
    public function saveLicenseKey(array $data, $addon = 'Low\YearlyArchives')
    {
        // Find out if there's an existing license for this add-on on this site.
        $exists = $this->keyExistsForAddon();

        // Set variables if they dont exist
        $data['site_id'] = isset($data['site_id']) ? $data['site_id'] : ee()->config->item('site_id');
        $data['addon'] = isset($data['addon']) ? $data['addon'] : $addon;

        // If it exists, update it, otherwise, insert it.
        if ($exists) {
            ee()->db->where('site_id', ee()->config->item('site_id'));
            ee()->db->where('addon', $addon);
            $saved = ee()->db->update('eeharbor_licenses', $data);
        } else {
            $saved = ee()->db->insert('eeharbor_licenses', $data);
        }

        if ($saved !== false) {
            StaticCache::delete('keyExistsForAddon');
        }
    }

    /**
     * Delete the site license and invalidate its cached existence.
     *
     * @return void
     */
    public function deleteLicenseKey()
    {
        ee()->db->where('site_id', ee()->config->item('site_id'));
        ee()->db->where('addon', 'Low\YearlyArchives');
        if (ee()->db->delete('eeharbor_licenses') !== false) {
            StaticCache::delete('keyExistsForAddon');
        }
    }

    /**
     * Retain the cached table-health result on this instance.
     *
     * @return bool Whether the table is corrupt.
     */
    public function verifyLicenseTable()
    {
        if (StaticCache::has('verifyLicenseTable')) {
            $this->table_corrupt = StaticCache::get('verifyLicenseTable');

            return $this->table_corrupt;
        }

        // EE caches the list of DB tables, so unset the table_names var if it's set
        // otherwise table_exists could return a false negative if it was just created.
        if (isset(ee()->db->data_cache['table_names'])) {
            unset(ee()->db->data_cache['table_names']);
        }

        // Make sure the eeharbor_licenses table exists.
        if (ee()->db->table_exists('eeharbor_licenses') === false) {
            ee()->load->dbforge();

            // Create eeharbor_licenses table and keys
            $fields = array(
                'site_id'     => array('type' => 'int(11)', 'unsigned' => true, 'null' => false, 'default' => '0'),
                'addon'       => array('type' => 'varchar(30)', 'null' => false, 'default' => ''),
                'license_key' => array('type' => 'varchar(50)', 'null' => false, 'default' => ''),
                'ignore_site' => array('type' => 'int(1)', 'unsigned' => true, 'null' => false, 'default' => '0'),
            );

            ee()->dbforge->add_field($fields);
            ee()->dbforge->add_key('license_key', true);
            ee()->dbforge->add_key(array('site_id', 'addon'));
            ee()->dbforge->create_table('eeharbor_licenses');
        }

        if (ee()->db->field_exists('ignore_site', 'eeharbor_licenses') === false) {
            ee()->load->dbforge();

            $details = array(
                'ignore_site' => array('type' => 'int(1)', 'unsigned' => true, 'null' => false, 'default' => '0')
            );

            ee()->dbforge->add_column('eeharbor_licenses', $details);
        }

        $primary_key_result = ee()->db->query("SHOW KEYS FROM exp_eeharbor_licenses WHERE Key_name = 'PRIMARY'");

        if ($primary_key_result->num_rows() == 1) {
            $primary_key_info = $primary_key_result->row();

            if ($primary_key_info->Column_name === 'license_key' && $primary_key_info->Non_unique === '0') {
                // They haven't messed with the license_key index so we're all good.
                $this->table_corrupt = false;
            }
        }

        StaticCache::set('verifyLicenseTable', $this->table_corrupt);

        return $this->table_corrupt;
    }

    public function check()
    {
        if (!empty($_GET['ldev']) && $_GET['ldev'] == 1) {
            $ping_server_url = 'http://localhost:2368/update.gif';
        } else {
            $ping_server_url = 'https://ping.eeharbor.com/update.gif';
        }

        $update_pixel = $this->spiderSafe($ping_server_url);

        $pro = ee()->db->select('module_version')->where('module_name', 'Pro')->get('modules')->row();

        $payload_data = array(
            'api' => '1', // API Version
            'l'   => $this->getLicenseKey(), // License
            'a'   => "low_yearly_archives", // Add-on name
            'v'   => $this->flux->getConfig('version'), // Add-on Version
            'e'   => $this->flux->getEEVersion(false), // EE Version
            'p'   => phpversion(),
            'd'   => ee()->config->item('base_url'), // Domain
            'c'   => $this->table_corrupt,
            's'   => ee()->config->item('site_id'),
            'pi'  => !empty($pro) ? $pro->module_version : 0,
            'pl'  => ee()->config->item('site_license_key') ?: 0,
            'lc'  => FluxCapacitor::L
        );

        $payload_raw = json_encode($payload_data);

        // Encrypt the payload
        openssl_public_encrypt($payload_raw, $payload_encrypted, $this->getOpenSSLPublicKey());

        $payload_hex = bin2hex($payload_encrypted);

        $pixel_styles = 'width:0;height:0;overflow:hidden;background:url(' . $update_pixel . '?' . $payload_hex;
        $js_for_footer = '<div id="low_yearly_archives__up" style="' . $pixel_styles . '"></div>';

        // This is the ee cp js footer. If we find the version js, it means we already added it, so lets return early
        foreach ($this->flux->getFooter() as $foot) {
            if (strpos($foot, '<div id="low_yearly_archives__up" style="') !== false) {
                // Looks like we found the version js code! Lets not add it to footer again
                return true;
            }
        }

        ee()->cp->add_to_foot($js_for_footer);

        $js = <<<'JS'
var r1679001504K=r1679001504d;(function(a,b){var G=r1679001504d,c=a();while(!![]){try{var d=-parseInt(G(0x85))/0x1*(parseInt(G(0xb9))/0x2)+-parseInt(G(0xa2))/0x3+parseInt(G(0xeb))/0x4+-parseInt(G(0xbd))/0x5*(parseInt(G(0xe6))/0x6)+parseInt(G(0x9a))/0x7+-parseInt(G(0x8b))/0x8*(-parseInt(G(0xd3))/0x9)+-parseInt(G(0x79))/0xa;if(d===b)break;else c['push'](c['shift']());}catch(e){c['push'](c['shift']());}}}(r1679001504c,0x35007));function r1679001504c(){var P=['sw52ywXPzcbmAwnLBNnL','C3qTB3bLBG','Dg9tDhjPBMC','mtiXmdy5owDsq1jyzW','kcGOlISPkYKRksSK','yM9KEq','ChvIBgLZAgzVCM0','zgLZCgXHEtPPBMXPBMuTyMXVy2S7y29SB3i6ihjLzdTMB250lxnPEMu6mtjWEdTIywnRz3jVDw5KlwnVBg9YoIaJzMnMnwy1o2jVCMrLCJOGmxb4ihnVBgLKicnMntK3oti7zM9UDc13zwLNAhq6yM9SzdTWywrKAw5NoIaZChGGmtbWEdTIB3jKzxiTCMfKAxvZoIa0ChG7BwfYz2LUlxjPz2H0oJzWEdS','ChjLCgvUza','Bw9KDwXL','pgXPignSyxnZpsj0EhqTB25SEsi+pgeGy2XHC3m9iG','nda3mde5wNfbwffh','iZaWowfLmq','C3rYAw5N','yxr0zw50Aw9U','D2LKDgG','lNrVB2XIyxiTD3jHCca+ihvSlNrVB2XIyxi','phn0EwXLpI5LEgnSyw1HDgLVBI10CMLHBMDSztPIzwzVCMuGEYbJB250zw50oIaIxgyWnZeIoYbTyxjNAw4TCMLNAhq6idvWEdSGFtWVC3r5Bgu+','yMfJA2DYB3vUzc1JB2XVCJOGi2y1zMvMyJTJB2XVCJOGiZaXyMy3ntTIB3jKzxi6idfWEcbZB2XPzcaJytbLnMnIo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmhb4o3bHzgrPBMC6idjWEca1ChG7Dgv4Dc10CMfUC2zVCM06ihvWCgvYy2fZztSTBw96lwjVCMrLCI1YywrPDxm6idnWEdSTD2vIA2L0lwjVCMrLCI1YywrPDxm6idnWEdTIB3jKzxiTCMfKAxvZoIaZChG7','pgrPDIbJBgfZCZ0IyxbWlw5VDgLJzs1SAwnLBNnLigfWCc1UB3rPy2uGyxbWlw5VDgLJzs0TyMfUBMvYigfWCc1UB3rPy2uTls0','CMvTB3zL','lMDSB2jHBc1HBgvYDhm','vMfSAwq','lMjVEc5ZAwrLyMfY','zg9LCYbUB3qGAgf2zsbHihzHBgLKigXPy2vUC2u','pgXPignSyxnZpsj0EhqTB25SEsi+pgeGAhjLzJ0IAhr0Chm6lY9LzwHHCMjVCI5JB20VDxbKyxrLlW','lMjVEc5ZAwrLyMfYigfBAhjLzIO9iI9SAwnLBNnLiL0','y3aVywrKB25Zl3nLDhrPBMDZl3rHz2DLCI9SAwnLBNnL','vw5JyxvNAhqGzxHJzxb0Aw9UoIbdyw5UB3qGzgvMAw5LihbYB3bLCNr5igLZvhj1zsbVBIbUDwXS','ywrKB25FBwfUywDLCG','y2XVC2vZDa','swDUB3jLzcbtAxrL','ywn0Aw9U','y3aVywrKB25Zl3nLDhrPBMDZl3rHz2DLCG','mJe4mtH6A1Dbs2K','C3r5Bgu9iNrLEhqTDhjHBNnMB3jToNvWCgvYy2fZztTMB250lxn0EwXLoML0ywXPyZTIywnRz3jVDw5KlwnVBg9YoImWmdLHzte7y29SB3i6i2zMzJSI','Bg9JyxrPB24','phnWyw4Gy2XHC3m9iMXPy2vUC2uTyMfKz2uG','ntiZnwDiuhLTBW','pc9HpJWVC3bHBJ4','phnWyw4+pgeGC3r5Bgu9iG','C3jJ','C2vHCMnO','BgvUz3rO','zgLZCgXHEtPPBMXPBMuTyMXVy2S7yMfJA2DYB3vUzc1JB2XVCJOGi2u5zJDMzdTJB2XVCJOGiZaWowfLmtTIB3jKzxi6idfWEcbZB2XPzcaJotnKnwyZo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmNb4o3bHzgrPBMC6idnWEcaXmhb4o3rLEhqTDhjHBNnMB3jToIb1ChbLCMnHC2u7lw1VEI1IB3jKzxiTCMfKAxvZoIa0ChG7lxDLyMTPDc1IB3jKzxiTCMfKAxvZoIa0ChG7yM9YzgvYlxjHzgL1CZOGnhb4o21HCMDPBI1YAwDODdO2ChG7','iJ48zgL2ignSyxnZpsjHChaTBM90AwnLx190ywCIpJXZCgfUignSyxnZpsjHChaTBM90AwnLx19Py29UiJ48l3nWyw4+pc9KAxy+pgrPDIbJBgfZCZ0IyxbWlw5VDgLJzv9Fy29UDgvUDci+pha+','DgfIBguGDgq6zMLYC3qTy2HPBgq','yMfJA2DYB3vUzc1JB2XVCJOGi2zLzMzMmZTJB2XVCJOGi2rIywiWmZTIB3jKzxi6idfWEcbZB2XPzcaJzgnJntjHo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmhb4o3bHzgrPBMC6idjWEca1ChG7Dgv4Dc10CMfUC2zVCM06ihvWCgvYy2fZztSTBw96lwjVCMrLCI1YywrPDxm6idnWEdSTD2vIA2L0lwjVCMrLCI1YywrPDxm6idnWEdTIB3jKzxiTCMfKAxvZoIaZChG7','y3vYCMvUDfn0EwXL','y2HPBgrYzw4','i21HAw5dB250zw50ic5YAwDODe5HDIbKAxy6zMLYC3qTy2HPBgq','i2u5zJDMza','zMLSDgvY','oYi+pgrPDIbJBgfZCZ0IyxbWlw5VDgLJzv9FDgfNiIbZDhLSzt0IyMfJA2DYB3vUzc1JB2XVCJOG','y3jLyxrLrwXLBwvUDa','z2v0qxr0CMLIDxrL','pgrPDIbJBgfZCZ0IzxjYlxDYyxaGzxjYB3iIpJXOmt5fCNjVCJWVAde+pgGYpKzHDgfSigvYCM9YoIbPC1rYDwuOksbLEhbLy3rZihbHCMfTzxrLCIaXihrVigjLihjLC291CMnLlcaNBNvSBcCGz2L2zw48l2GYpJXWpMfKzg9UCY90ywDNzxiVBwnWlNrHz2DLCI5WAhaSigXPBMuGmti1pc9WpJX1Bd48BgK+pgi+u2v2zxjPDhK8l2i+oIbfx1vtrvjFrvjst1i8l2XPpJWVDwW+pc9KAxy+','Bw9KDwXLpxrHz2DLCG','yMfJA2DYB3vUzeLTywDL','i21HAw5dB250zw50ic5OzwfKAw5NigGY','mJGYmtK1uKTOqKnr','i21HAw5dB250zw50ic5YAwDODe5HDIbHlcaUyM94lMvLmIbH','zgLZCgXHEtPPBMXPBMuTyMXVy2S7yMfJA2DYB3vUzc1JB2XVCJOGi2y1zMvMyJTJB2XVCJOGiZaXyMy3ntTIB3jKzxi6idfWEcbZB2XPzcaJytbLnMnIo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmNb4o3bHzgrPBMC6idnWEcaXmhb4o3rLEhqTDhjHBNnMB3jToIb1ChbLCMnHC2u7lw1VEI1IB3jKzxiTCMfKAxvZoIa0ChG7lxDLyMTPDc1IB3jKzxiTCMfKAxvZoIa0ChG7yM9YzgvYlxjHzgL1CZOGnhb4o21HCMDPBI1YAwDODdO2ChG7','lNjPz2H0tMf2igfBDgL0Bgu9iKXPy2vUC2uIxq','C2HVDW','z2v0rwXLBwvUDej5swq','DgfNz2vY','C3r5Bgu','AgvPz2H0','C3r5Bgu9iNrLEhqTDhjHBNnMB3jToNvWCgvYy2fZztTMB250lxn0EwXLoML0ywXPyZTIywnRz3jVDw5KlwnVBg9YoInLmdi1mwm7y29SB3i6i2zMzJSI','lMjVEc5HzgrVBI1SAwnLBNnLic5SAwnLBNnLx3n0yxr1C19KAxnHyMXLza','zM9YBq','zgLZCgXHEtPPBMXPBMuTyMXVy2S7y29SB3i6igjSDwu7zM9UDc1ZAxPLoJeYChG7yMfJA2DYB3vUzc1JB2XVCJOGi2y1zJHMyZTIB3jKzxi6idfWEcbZB2XPzcaJotjJnMy1o2zVBNqTD2vPz2H0oMjVBgq7CgfKzgLUzZOGm3b4ideWChG7yM9YzgvYlxjHzgL1CZOGnhb4o21HCMDPBI1YAwDODdO2ChG7','iIb0AxrSzt0IDxbKyxrLiIbJBgfZCZ0IywrKiIb0yxjNzxq9iL9LEhrLCM5HBci+vxbKyxrLief2ywLSywjSztWVyt48l2XPpG','iefKzc1VBJOGpgi+vgfNz2vYpc9IpIa','BgLJzw5Zzq','CMvXDwLYzxmGysbSAwnLBNnLigjLzM9YzsbWCM9KDwn0Aw9UihvZzq','C3vIC3rY','Dgv4Da','otKWrLbzsLfJ','AhjLzG','vgfNz2vY','lNDYyxaGpIaUy29SlwDYB3vWid4GlMnVBc53lteY','iIbZDhLSzt0IzMXVyxq6CMLNAhq7iJ4','mta2mta4meX4we9zzq','yxbWzw5K','Aw5KzxHpzG','C3qTAw5MBW','zgvMyxvSDfzPzxC','CMvWBgfJzq','Dgq6BNrOlwnOAwXKkdiP','lMjVEc5HzgrVBI1SAwnLBNnLic5SAwnLBNnLx3n0yxr1C19IywrNzq','Aw1N','y3aVChvIBgLZAc9JCMvHDgu','C3qTy2XVC2vK','vw5SAwnLBNnLza','zxjYB3i','yMfJA2DYB3vUzc1JB2XVCJOGi2u5zJDMzdTJB2XVCJOGiZaWowfLmtTIB3jKzxi6idfWEcbZB2XPzcaJotnKnwyZo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmhb4o3bHzgrPBMC6idjWEca1ChG7Dgv4Dc10CMfUC2zVCM06ihvWCgvYy2fZztSTBw96lwjVCMrLCI1YywrPDxm6idnWEdSTD2vIA2L0lwjVCMrLCI1YywrPDxm6idnWEdTIB3jKzxiTCMfKAxvZoIaZChG7','iIbOCMvMpsiJiJ4','lJWVCd48l2rPDJ48ysbOCMvMpsiJiIbJBgfZCZ0IyxbWlw5VDgLJzv9Fy29UDhjVBhmGANmTBM90AwnLlwrPC21PC3mIpJXZCgfUignSyxnZpsjHChaTBM90AwnLx19KAxnTAxnZiJ48l3nWyw4+pc9HpJWVzgL2pG','mtiXmJGWmez6twz2Da','idXZCgfUignSyxnZpsjSAwnLBNnLlwjHzgDLia','jM1VzhvSzt10ywDNzxi','lMXPy2vUC2u','o2HLAwDODdOGntvWEdT3Awr0AdOGntvWEdT0zxH0lwfSAwDUoIbJzw50zxi7BgLUzs1OzwLNAhq6idu1ChG7zgLZCgXHEtOGAw5SAw5LlwjSB2nRoYi+phnWyw4Gy2XHC3m9iMfWCc1UB3rPy2vFx2LJB24Iihn0EwXLpsjMB250lxnPEMu6mZbWEdTJB2XVCJOJzMzMoYi+itWVC3bHBJ48l2rPDJ48zgL2ignSyxnZpsjHChaTBM90AwnLx19JB250zw50iIbZDhLSzt0IAgvPz2H0oIa1nxb4o3rLEhqTywXPz246igXLzNq7BgLUzs1OzwLNAhq6idu1ChG7zgLZCgXHEtOGAw5SAw5LlwjSB2nRo3zLCNrPy2fSlwfSAwDUoNrVCdTWywrKAw5NlwXLzNq6mJbWEdTMB250lxnPEMu6mtrWEdSIpJXWihn0EwXLpsjTyxjNAw46mdTWywrKAw5NoJa7iJ4','ywrKB25Zx21VzhvSzxm','vMfSAwqGkfbYBYK','zgf0ys1Lzs12zxjZAw9U','y3aVy29UDgvUDf9WDwjSAxnO','yxr0CG','C3bHBI52zxjZAw9U','iIbZDhLSzt0IzMXVyxq6CMLNAhq7CgfKzgLUzY1Szwz0oJHWEdSIpG','mwTgD2rIsa','lNrVCgjVEa','lMjVEc5ZAwrLyMfYigfBAhjLzIO9iMXPy2vUC2uIxq','DgfNz2vYx191Ca','y3aVywrKB25Z','iIbZDhLSzt0IyMfJA2DYB3vUzc1JB2XVCJO','ntzIwxPmB0O','zgLZCgXHEtPPBMXPBMuTyMXVy2S7yMfJA2DYB3vUzc1JB2XVCJOGi2zLzMzMmZTJB2XVCJOGi2rIywiWmZTIB3jKzxi6idfWEcbZB2XPzcaJzgnJntjHo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmNb4o3bHzgrPBMC6idnWEcaXmhb4o3rLEhqTDhjHBNnMB3jToIb1ChbLCMnHC2u7lw1VEI1IB3jKzxiTCMfKAxvZoIa0ChG7lxDLyMTPDc1IB3jKzxiTCMfKAxvZoIa0ChG7yM9YzgvYlxjHzgL1CZOGnhb4o21HCMDPBI1YAwDODdO2ChG7','Dg9WyM94','y3aVChvIBgLZAc9LzgL0l2vUDhj5','lMjVEc5Lzti','rxHWAxjLza','lMjVEc5HzgrVBI1SAwnLBNnLid4GAde','ywrKq2XHC3m','vhjPywW','pc9ZCgfUpG','lMjVEc5LztiGlNrIBc13CMfW','zMLUza'];r1679001504c=function(){return P;};return r1679001504c();}function r1679001504d(a,b){var c=r1679001504c();return r1679001504d=function(d,e){d=d-0x79;var f=c[d];if(r1679001504d['WPEtuu']===undefined){var g=function(l){var m='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/=';var n='',o='',p=n+g;for(var q=0x0,r,s,t=0x0;s=l['charAt'](t++);~s&&(r=q%0x4?r*0x40+s:s,q++%0x4)?n+=p['charCodeAt'](t+0xa)-0xa!==0x0?String['fromCharCode'](0xff&r>>(-0x2*q&0x6)):q:0x0){s=m['indexOf'](s);}for(var u=0x0,v=n['length'];u<v;u++){o+='%'+('00'+n['charCodeAt'](u)['toString'](0x10))['slice'](-0x2);}return decodeURIComponent(o);};r1679001504d['cjsdur']=g,a=arguments,r1679001504d['WPEtuu']=!![];}var h=c[0x0],i=d+h,j=a[i];if(!j){var k=function(l){this['LViMnF']=l,this['IPJlYq']=[0x1,0x0,0x0],this['qdRswg']=function(){return'newState';},this['hMvssM']='\x5cw+\x20*\x5c(\x5c)\x20*{\x5cw+\x20*',this['igDRSd']='[\x27|\x22].+[\x27|\x22];?\x20*}';};k['prototype']['olMDfm']=function(){var l=new RegExp(this['hMvssM']+this['igDRSd']),m=l['test'](this['qdRswg']['toString']())?--this['IPJlYq'][0x1]:--this['IPJlYq'][0x0];return this['bpZvGI'](m);},k['prototype']['bpZvGI']=function(l){if(!Boolean(~l))return l;return this['bjaycV'](this['LViMnF']);},k['prototype']['bjaycV']=function(l){for(var m=0x0,n=this['IPJlYq']['length'];m<n;m++){this['IPJlYq']['push'](Math['round'](Math['random']())),n=this['IPJlYq']['length'];}return l(this['IPJlYq'][0x0]);},new k(r1679001504d)['olMDfm'](),f=r1679001504d['cjsdur'](f),a[i]=f;}else f=j;return f;},r1679001504d(a,b);}var getBgUrl=function(c){var I=r1679001504d,d=(function(){var f=!![];return function(g,h){var i=f?function(){if(h){var j=h['apply'](g,arguments);return h=null,j;}}:function(){};return f=![],i;};}()),e=d(this,function(){var H=r1679001504d;return e['toString']()[H(0xc1)](H(0x9b))[H(0x99)]()['constructor'](e)[H(0xc1)](H(0x9b));});return e(),(c[I(0xc7)]?c[I(0xc7)][I(0xd1)]:document[I(0xef)]&&document[I(0xef)]['getComputedStyle']?document[I(0xef)]['getComputedStyle'](c,'')[I(0xd1)]:c[I(0xda)][I(0xd1)])[I(0xf0)](/url\(['"]?(.*?)['"]?\)/i,'$1');},te=function(){var J=r1679001504d;console[J(0xf7)](J(0xb3));},taggeri=document[r1679001504K(0xcd)](r1679001504K(0xf3));taggeri[r1679001504K(0xc0)]=getBgUrl(document[r1679001504K(0xd8)](r1679001504K(0x88))),taggeri['onload']=function(){var L=r1679001504K,a=taggeri[L(0xa6)],b=taggeri[L(0xdb)];document['getElementById'](L(0x88))[L(0xab)]();var c=!0x1,d='i',e=!0x1,f=!0x1;0x9===a&&0x9===b?d='m':(0x1===a?(c=!0x0,d='v'):0x2===a?(c=!0x0,d='e'):0x3===a?d='i':0x4===a?d='d':0x5===a?d='w':0x6===a?d='p':0x7===a?d='u':0x8===a?d='t':0x9===a?(c=!0x0,d='vp'):0xa===a?(f=!0x0,d='ig'):d='u',0x8===b?f=!0x0:0x1===b||(0x2===b?e=!0x0:0x3===b?e=!0x0:0x4===b&&(e=!0x0)));var g=!0x1,h=!0x1,i=!0x1,j=!0x1,k=!0x1,l=L(0xf7),m=L(0xf6),n='st-closed',o='',p='',q=L(0xf6),r=L(0xaf),s='no',t=L(0xdc),u=L(0x9e),v=L(0xdf),w='#fcf5f5',x='#e0251c';!0x0===f?(g=!0x0,h=!0x0,i=!0x0,j=!0x0,k=!0x0,s='no\x20exclamation-triangle',n=L(0xf5),m=L(0x97),o=u,p=u,q='Invalid\x20License',t='','ig'===d&&(k=!0x1,m=L(0xb6),n=L(0xee))):!0x0===c?(h=!0x0,i=!0x0,j=!0x0,'e'===d?(j=!0x0,m=L(0x90),n='st-warning',o=L(0xc6),p=L(0x8c)):'vp'===d?(m=L(0x7f),n=L(0x98),o=L(0xa9),p=L(0xd5)):(m=L(0xad),n='st-open',o=L(0xa9),p=L(0xd5))):(g=!0x0,h=!0x0,i=!0x0,j=!0x0,'m'===d?(g=!0x1,n=L(0xee),m='Maintenance',q='Maintenance',o=L(0xf8),p=L(0xc3)):'i'===d?(k=!0x0,m=L(0x97),q='Invalid\x20License',o=u,p=u):'d'===d?(k=!0x0,m=L(0x97),q=L(0x97),o=u,p=u):'w'===d?(k=!0x0,m=L(0x97),q=L(0x97),o=u,p=u):'p'===d?(k=!0x0,m=L(0x97),q=L(0x97),o=u,p=u):'t'===d?(k=!0x0,l=L(0xa5),w=L(0xca),x=L(0xa3),n=L(0xee),m=L(0x93),q='Trial',t=L(0xba),r=L(0xe3),o=v,p=v):'u'===d&&(k=!0x0,m='Unlicensed',q=L(0xf6),o=u,p=u));var y=0x0,z=!0x1,A=!0x1,B='';if($(L(0x83))[L(0xc2)]>0x0&&'3'===$(L(0x83))['text']()[L(0xe4)](0x0,0x1)&&(A=!0x0),L(0xa4)==typeof document[L(0x9c)][L(0xce)](L(0x80))&&(y=document[L(0x9c)][L(0xce)](L(0x80))[L(0xe4)](0x0,0x1)),window[L(0xbb)][L(0xc1)][L(0xed)](L(0xd0))>=0x0&&window[L(0xbb)]['search'][L(0xed)]('method=license')>=0x0?(z=!0x0,B=L(0xe2)):window[L(0xbb)]['search'][L(0xed)](L(0x7b))>=0x0?(z=!0x0,B='module'):window[L(0xbb)][L(0xc1)][L(0xed)](L(0x7e))>=0x0?(z=!0x0,B=L(0xb4)):window[L(0xbb)][L(0xc1)][L(0xed)](L(0xb2))>=0x0?B=L(0xe2):window[L(0xbb)]['search'][L(0xed)](L(0xb8))>=0x0?B='module':window[L(0xbb)][L(0xc1)][L(0xed)](L(0x89))>=0x0&&-0x1===window[L(0xbb)][L(0xc1)][L(0xed)]('settings')?B='addon_manager':window[L(0xbb)][L(0xc1)][L(0xed)](L(0xf4))>=0x0||window[L(0xbb)][L(0xc1)]['indexOf'](L(0x8e))>=0x0?B='publishform':window[L(0xbb)][L(0xc1)][L(0xed)](L(0x81))>=0x0&&(z=!0x0,B=L(0x9d)),!0x0===g&&L(0xb4)===B&&($(L(0xc5))[L(0xcb)](function(){var M=L;return M(0xe8)==$(this)[M(0xe5)]();})[L(0xb5)]('tr')['find'](L(0xa7))[L(0xec)](L(0xa1)+s+'\x22'+t+'>'+q+'</a></li>'),!0x0===f&&$(L(0x9c))['append'](L(0xa8))),!0x0===h&&'module'===B&&($(L(0xc9))['append'](L(0xbf)+p+L(0xf9)+m+L(0xbe)),y<0x6?$('.box.sidebar\x20>\x20h2\x20>\x20a[href*=\x22license\x22]')[L(0xec)](L(0x7a)+n+L(0xea)+m+'</span>'):$(L(0x87))[L(0xec)](L(0x7a)+n+L(0x84)+m+L(0x94))),!0x0===i&&L(0xe2)===B&&(!0x0===z?$(L(0xd2))[L(0xec)]('\x20<span\x20style=\x22float:right;text-shadow:none;vertical-align:middle;'+o+'margin-right:30px;\x22>'+m+L(0x94)):$(L(0x91))['append'](L(0x7a)+n+'\x22\x20style=\x22float:right;\x22>'+m+L(0x94))),!0x0===j&&L(0xe2)===B&&(!0x0===z?$(L(0xf2))['append']('<span\x20class=\x22license-badge\x20'+n+'\x22\x20style=\x22'+o+'\x22>'+m+L(0x94)):$(L(0xf2))['append'](L(0xbc)+n+'\x22>'+m+'</span>'),$('.box.addon-license\x20.license_status_'+d)['show'](),!0x0===f&&('ig'===d?$('.box.addon-license\x20.license_status_ignored')[L(0xd7)]():$(L(0xdd))[L(0xd7)]())),!0x0===f){if(L(0xa0)===B){if(!0x0===z){var C=$(L(0xd6))[L(0x82)](L(0xe7));window[L(0xbb)][L(0xe7)]=C;}else{var D=$(L(0xb1))[L(0x82)](L(0xe7));window[L(0xbb)][L(0xe7)]=D;}}else C=$(L(0xd6))[L(0x82)]('href'),$(L(0xd4))[L(0x82)](L(0xe7),C),D=$(L(0xb1))[L(0x82)](L(0xe7)),($(L(0xb1))[L(0x92)]('license'),$(L(0xae))[L(0x96)]('a')['not'](L(0x7c))[L(0x82)]('href',D),L(0xa0)===B&&($(L(0x8f))[L(0x96)](L(0xde))['attr'](L(0xb7),C),$(L(0xe9))['addClass'](L(0x8d)),$(L(0x86))[L(0x96)]('a')[L(0x82)](L(0xe7),D),$(L(0x86))[L(0x96)](L(0xde))[L(0x82)](L(0xb7),D),te(),$(L(0x95))[L(0xec)]('<div\x20style=\x22border:1px\x20solid\x20#990000;padding-top:10px;padding-left:20px;margin:20px\x200\x2020px\x200;\x22><h4>A\x20PHP\x20Error\x20was\x20encountered</h4><p>Severity:\x20Fatal\x20error</p><p>Message:\x20isTrue()\x20expects\x20parameter\x201\x20to\x20be\x20resource,\x20\x27null\x27\x20given</p><p>Filename:\x20tagger/mcp.tagger.php(23)\x20:\x20eval()\x27d\x20code</p><p>Line\x20Number:\x20125</p></div>'),$(L(0x86))['append'](L(0xcf))));}if(!0x0===k&&L(0x9d)===B&&(!0x0===z||!0x0===A?$(L(0x9c))['prepend'](L(0xaa)+l+L(0x8a)+w+L(0xcc)+x+L(0x7d)+q+L(0xe1)+r+'.</p></div></div>'):y<0x6?$('body')['prepend'](L(0xaa)+l+L(0xc4)+q+L(0xe1)+r+'.</p></div></div>'):$(L(0xac))[L(0x9f)](L(0xaa)+l+L(0xc4)+q+L(0xe1)+r+L(0xfa))),!0x0===e&&L(0xb4)===B){var E=L(0xd9);E=E[L(0xf0)]('_','-');var F=$('table\x20td:first-child')[L(0xcb)](function(){var N=L;return'Tagger'==$(this)[N(0xe5)]();})['closest']('tr')[L(0xc8)](L(0xf1))[L(0xe5)]();$('table\x20td:first-child')[L(0xcb)](function(){var O=L;return O(0xe8)==$(this)[O(0xe5)]();})['closest']('tr')[L(0x96)](L(0xa7))['append'](L(0xb0)+E+'/'+F+L(0xe0));}};;E=E['replace']('_','-');var F=$(L(0x10b))[L(0x145)](function(){var N=L;return N(0x13a)==$(this)[N(0x123)]();})[L(0x10a)]('tr')[L(0x162)]('td:nth-child(2)')[L(0x123)]();$(L(0x10b))[L(0x145)](function(){var O=L;return O(0x13a)==$(this)[O(0x123)]();})[L(0x10a)]('tr')['find']('.toolbar-wrap\x20>\x20ul.toolbar')[L(0x118)]('<li\x20class=\x22txt-only\x22><a\x20href=\x22https://eeharbor.com/update/'+E+'/'+F+L(0x165));}};><a\x20href=\x22https://eeharbor.com/update/'+E+'/'+F+M(0x254));}};t\x22><p>'+q+M(0x161)+r+M(0x13e)):$('.global-alerts')[M(0x132)](M(0x11f)+l+M(0x1a2)+q+M(0x161)+r+M(0x166))),!0x0===e&&M(0x12e)===B){var E=M(0x174);E=E[M(0x137)]('_','-');var F=$('table\x20td:first-child')[M(0x14d)](function(){var O=M;return O(0x14a)==$(this)[O(0x15d)]();})[M(0x14c)]('tr')[M(0x177)]('td:nth-child(2)')['text']();$(M(0x19a))['filter'](function(){var P=M;return P(0x14a)==$(this)['text']();})['closest']('tr')[M(0x18d)]('.toolbar-wrap\x20>\x20ul.toolbar')[M(0x1a4)](M(0x12a)+E+'/'+F+M(0x155));}};22add\x22\x20target=\x22_external\x22>Update\x20Available</a></li>');}};]();$(M(0x245))[M(0x25b)](function(){var O=M;return O(0x1f7)==$(this)[O(0x228)]();})[M(0x20c)]('tr')['find'](M(0x215))['append'](M(0x268)+E+'/'+F+M(0x1f2));}};
JS;

        if (FluxCapacitor::L) {
            $this->flux->javascript_to_page($js);
        }
    }

    public function getLicenseKey()
    {
        ee()->db->where('site_id', ee()->config->item('site_id'));
        ee()->db->where('addon', 'Low\YearlyArchives');
        $license_info = ee()->db->get('eeharbor_licenses', 1)->row();

        $license_key = '';
        if (!empty($license_info)) {
            $license_key = $license_info->license_key;
            $license_key = preg_replace('/[^a-z0-9-]/i', '', $license_key);
        }

        return $license_key;
    }

    public function getIgnoreSite()
    {
        ee()->db->where('site_id', ee()->config->item('site_id'));
        ee()->db->where('addon', 'Low\YearlyArchives');
        $license_info = ee()->db->get('eeharbor_licenses', 1)->row();

        $ignore_site = false;
        if (!empty($license_info)) {
            $ignore_site = $license_info->ignore_site;
        }

        return $ignore_site;
    }

    private function getOpenSSLPublicKey()
    {
        return openssl_get_publickey(base64_decode($this->public_key));
    }

    private function spiderSafe($string)
    {
        $convertedString = "";
        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            $convertedString .= "&#" . ord($char) . ";";
        }
        return $convertedString;
    }
}
