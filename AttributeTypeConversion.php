<?php
/**
 * AttributeTypeConversion magento extension
 * 
 * NOTICE OF LICENSE
 * 
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/mit-license.php
 * 
 * @category       Magento
 * @package        AttributeTypeConversion
 * @copyright      Copyright (c) 2016
 * @license        http://opensource.org/licenses/mit-license.php MIT License
 */
 /**
 * Script change attribute type of magento products
 *
 * @category       Magento
 * @package        AttributeTypeConversion
 * @author         Jyotiranjan Biswal<biswal@jyotiranjan.in>
 */
define('MAGENTO', realpath(dirname(__FILE__)));
require_once MAGENTO . '/app/Mage.php';
Mage::app();

$manageAttribute = new manageAttribute();
class manageAttribute{
    private $_attributeCodesArray;
    private $_attributeCodes;
    private $_success = false;
    private $_frontendInput;
    private $_backendType;
    private $_message;
    private $_from;
    private $_to;
    /*
    * Initialize the Mage application
    */
    function __construct()
    {
        // Increase maximum execution time to 4 hours
        ini_set('max_execution_time', 14400);
        // Set working directory to magento root folder
        chdir(MAGENTO);
        // Make files written by the profile world-writable/readable
        
        Mage::setIsDeveloperMode(true);
        ini_set('display_errors', 1);
        umask(0);
        Mage::app('admin');
        Mage::register('isSecureArea', 1);

        // Run the main application
        $this->_runMain();
    }
    /*
    * Run the main application and call the appropriate function
    * depending on the command.
    */
    private function _runMain()
    {
        $this->_redirectUrl = $_SERVER['PHP_SELF'];
        
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if(!$this->validate()){
            $messageType = 'error';
            $this->DisplayForm($messageType);
            return false;
            }
            $this->changeInEavAttributeTable($this->_backendType,$this->_frontendInput);
            //return false;
            $this->changeAttributeType($this->_from,$this->_to);
        }
        $messageType = 'success';
        $this->DisplayForm($messageType);
	
    }
    function validate(){
        if((isset($_POST['attribute_codes']) && $_POST['attribute_codes'] != '')){
            $this->_attributeCodes = $_POST['attribute_codes'];
            $this->_attributeCodesArray = explode(',',(string)$this->_attributeCodes);
        }else{
            $this->_message = 'Please enter attribute code separated by comma';
            return false;
        }
        if((isset($_POST['frontend_input']) && $_POST['frontend_input'] != '')){
            $this->_frontendInput = $_POST['frontend_input'];
        }else{
            $this->_message = 'Please enter attribute type';
            return false;
        }
        if($this->_frontendInput == 'price'){
            $this->_from = 'catalog_product_entity_varchar';
            $this->_to = 'catalog_product_entity_decimal';
            $this->_backendType = 'decimal';
        }else if($this->_frontendInput == 'text'){
            $this->_from = 'catalog_product_entity_decimal';
            $this->_to = 'catalog_product_entity_varchar';
            $this->_backendType = 'varchar';
        }
        return true;
    }
    function changeInEavAttributeTable($backendType,$frontendInput){
        $array = $this->_attributeCodesArray;
        $connection = $this->_getConnection('core_write');	
        foreach($this->_attributeCodesArray as $code){
            $sql = 'UPDATE '.$this->_getTableName('eav_attribute').' SET frontend_input = '.'"'.$frontendInput.'"'.', backend_type = '.'"'.$backendType.'"'.' WHERE attribute_code = '.'"'.$code.'"';
            $connection->query($sql);
        }
        $attributeIds = (string) implode(',', $this->_getAttributeIds());
        $this->_updatedMessage = 'eav_attribute table is UPDATED with attribute codes '.$this->_attributeCodes.' and attribute Ids '.$attributeIds;
    }
    function changeAttributeType($from,$to){
        # EDIT HERE...Note the single quotes inside the double quotes. This is necessary unless you modify the function yourself
        # Note that these attribute codes are those attributes whose type is to be changed.
        //$this->_attributeCodesArray = array("'purchase_price'");
        
        $connection = $this->_getConnection('core_write');
        $attributeIds = (string) implode(',', $this->_getAttributeIds());
        $entityTypeId = (int) $this->_getEntityTypeId();
        $sql = 'SELECT * FROM ' . $this->_getTableName($this->_from) . ' WHERE attribute_id IN ('.$attributeIds.') AND entity_type_id = '.$entityTypeId;
        $rows = $connection->fetchAll($sql);
        $insertCount = 1;
        $deleteCount = 1;
        $insertOutput = '';
        $deleteOutput = '';
        foreach($rows as $row){
            $checkIfDecimalValueExists = $this->_checkIfDecimalValueExists($row);
            if(!$checkIfDecimalValueExists){
            $sql = 'INSERT INTO ' . $this->_getTableName($this->_to) . ' (`entity_type_id`,`attribute_id`,`store_id`,`entity_id`,`value`)
                    VALUES (?,?,?,?,?)';
            $price = $row['value'];
            $price = trim(str_replace(',', '.', $price));
            $connection->query($sql, array($row['entity_type_id'], $row['attribute_id'], $row['store_id'], $row['entity_id'], $price));
            $insertOutput .= $insertCount . '> INSERTED::' . $connection->lastInsertId() . ' :: ' .$row['value'] . ' => ' . $price . '<br />';
            $insertCount++;
            }
            $sql = 'DELETE FROM ' . $this->_getTableName($this->_from) . ' WHERE value_id = ?';
            $connection->query($sql, $row['value_id']);
            $deleteOutput .= $deleteCount . '> DELETED::'.$row['value_id'].'<br />';
            $deleteCount++;
        }
        $this->_message .= '==========UPDATED===============================<br />';
        $this->_message .= '<strong>'.$this->_updatedMessage.'</strong><br />';
        $this->_message .= '==================================================<br />';
        $this->_message .= '==========INSERTED=======================================<br />';
        $this->_message .= '<strong>INSERTED from table '.$this->_from.' to table '.$this->_to.'</strong><br />';
        $this->_message .= $insertOutput;
        $this->_message .= '=================================================<br />';
        $this->_message .= '==========DELETED=======================================<br />';
        $this->_message .= '<strong>DELETED from '.$this->_from.'</strong><br />';
        $this->_message .= $deleteOutput;
        $this->_message .= '=================================================<br />';
    }
    
    function _getTableName($tableName){
        return Mage::getSingleton('core/resource')->getTableName($tableName);
    }
    
    function _getConnection($type = 'core_read'){
        return Mage::getSingleton('core/resource')->getConnection($type);
    }
    
    function _getAttributeIds(){
        //global $_attributeCodes;
        $attributeCodes = '';
        foreach($this->_attributeCodesArray as $codes){
            $attributeCodes .= "'".$codes."',";
        }
        $attributeCodes = rtrim($attributeCodes, ",");
        
        //$attributeCodes = (string) implode(',', $this->_attributeCodesArray);
        
        $connection = $this->_getConnection('core_read');
        $sql = "SELECT attribute_id
                FROM " . $this->_getTableName('eav_attribute') . "
                WHERE attribute_code
                IN (
                    ". $attributeCodes . "
                )";
        //SELECT attribute_id FROM eav_attribute WHERE attribute_code IN('custom_price_new','purchase_price');
        return $connection->fetchCol($sql);
    }
    
    function _getEntityTypeId(){
        $connection = $this->_getConnection('core_read');
        $sql		= "SELECT entity_type_id FROM " . $this->_getTableName('eav_entity_type') . " WHERE entity_type_code = 'catalog_product'";
        return $connection->fetchOne($sql);
    }
    
    function _checkIfDecimalValueExists($row){
        $connection = $this->_getConnection('core_write');
        $sql		= 'SELECT COUNT(*) FROM ' . $this->_getTableName($this->_to) . ' WHERE attribute_id = ? AND entity_type_id = ? AND store_id = ? AND entity_id = ?';
        $result		= $connection->fetchOne($sql, array($row['attribute_id'], $row['entity_type_id'], $row['store_id'], $row['entity_id']));
        return $result > 0 ? true : false;
    }
    private function DisplayForm($messageType,$logMessage='')
    {
        // Set character set to UTF-8
        header("Content-Type: text/html; charset=UTF-8");
        ?>
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
            <meta http-equiv="Content-type" content="text/html;charset=UTF-8" />
            <title>Change attribute type from price type to text type and vice versa</title>
            <style type="text/css">
                .main_div{margin:0 auto;width: 60%;}
                .input_type{width: 98%;}
                .main_table{margin: 1em auto;width: 100%;}
                .second{width: 70%;}
                .first{width: 30%;text-align: right}
            </style>
            </head>
            <body>
            <form method="post" action="">
                <h2 style="text-align:center;">Welcome to change atribute type</h2>
                <div style="clear:both;"></div>
                <div class="main_div">
                <fieldset>
                    <legend>Change attribute type from price type to text type and vice versa</legend>
                    <?php if($_SERVER["REQUEST_METHOD"] == "POST"): ?>
                    <table style="margin: 1em auto;" cellpadding="2">
                        <tr>
                        <td>
                        <?php
                        if($logMessage){
                            echo "<pre>";
                            print_r($logMessage);
                            echo "</pre>";
                        }
                        ?>
                        </td>
                        <?php if($messageType == 'success'): ?>
                        <td style="color: #48961B;"><?php echo $this->_message; ?></td>
                        <?php elseif($messageType == 'error'): ?>
                        <td style="color: #F70E0E;"><?php echo $this->_message; ?></td>
                        <?php endif ?>
                        </tr>
                    </table>
                    <?php endif; ?>
                    <table class="main_table" cellpadding="2">
                    <tr>
                        <th class="first" style="">Enter attribute codes separated by comma: </th>
                        <th class="second"><input class='input_type' type="text" name='attribute_codes' value=""/></th>
                    </tr>
                    <tr>
                        <th class="first">Enter attribute type to convert: </th>
                        <th class="second"><input class='input_type' type="text" name='frontend_input' value=""/></th>
                    </tr>
                    <tr>
                        <th class="first"></th>
                        <th class="second"><input type="submit" name="submit" value="Submit"></th>
                    </tr>
                    </table>
                </fieldset>
                </div>
            </form>
            </body>
        </html>
        <?php
    }
}