<?php
/**
 * Faonni
 *  
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade module to newer
 * versions in the future.
 * 
 * @package     SmartCategoryConfigurable
 * @copyright   Copyright (c) 2017 Karliuka Vitalii(karliuka.vitalii@gmail.com) 
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Faonni\SmartCategoryConfigurable\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Config;

/**
 * Faonni_SmartCategory ConfigurableProductsProvider
 */
class ConfigurableProductsProvider
{
    /** 
     * @var \Magento\Framework\App\ResourceConnection 
     */
    private $_resource;
    
    /**
     * @var \Magento\Catalog\Model\Config
     */
    protected $_config;    

    /**
     * @var array
     */
    private $_productIds = [];
    
    /**
     * Catalog product visibility
     *
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    protected $_catalogProductVisibility;   
    
    /**
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Catalog\Model\Product\Visibility $catalogProductVisibility 
     * @param \Magento\Catalog\Model\Config $config
     */
    public function __construct(
		ResourceConnection $resource,
        Visibility $catalogProductVisibility,
        Config $config
	) {
        $this->_resource = $resource;
        $this->_catalogProductVisibility = $catalogProductVisibility;        
        $this->_config = $config;
    }

    /**
     * Retrieve display products pairs ids
     * 
     * @param array $ids
     * @return array
     */
    public function getDisplayIds(array $ids)
    {
        $key = md5(json_encode($ids));       
        if (!isset($this->_productIds[$key])) {
            $connection = $this->_resource->getConnection();           
			$select = $connection
				->select()
				->from(
					['e' => $this->_resource->getTableName('catalog_product_entity')], 
					['e.entity_id', 'display_id' => new \Zend_Db_Expr('IF(c.value_id, p.entity_id, IF(s.entity_id, 0, NULL))')]
				)
				->joinLeft(
					['l' => $this->_resource->getTableName('catalog_product_super_link')],
					'l.product_id=e.entity_id', 
					[]
				)  				                  
				->joinLeft(
					['p' => $this->_resource->getTableName('catalog_product_entity')],
					'l.parent_id=p.entity_id', 
					[]
				) 
				->joinLeft(
					['c' => $this->_resource->getTableName('catalog_product_entity_int')],
					join(
						' AND ',
						[
							'c.entity_id = p.entity_id',
							'c.store_id = "0"',
							$connection->quoteInto('p.type_id = ?', Configurable::TYPE_CODE),
							$connection->quoteInto('c.attribute_id = ?', $this->getVisibilityAttributeId()),
							$connection->quoteInto('c.value IN(?)', $this->_catalogProductVisibility->getVisibleInSiteIds())
						]
					),					
					[]
				) 
				->joinLeft(
					['s' => $this->_resource->getTableName('catalog_product_entity_int')],
					join(
						' AND ',
						[
							new \Zend_Db_Expr('c.value_id IS NULL'),
							's.entity_id = e.entity_id',
							's.store_id = "0"',
							$connection->quoteInto('s.attribute_id = ?', $this->getVisibilityAttributeId()),
							$connection->quoteInto('s.value IN(?)', $this->_catalogProductVisibility->getVisibleInSiteIds())
						]
					),					
					[]
				) 
				->where('e.entity_id IN (?)', $ids); 	
            $this->_productIds[$key] = $connection->fetchPairs($select);
        }        
        return $this->_productIds[$key];
    }
	
    /**
     * Retrieve visibility attribute id
     * 
     * @return int
     */
    public function getVisibilityAttributeId()
    {
        return $this->_config->getAttribute(Product::ENTITY, 'visibility')->getId();	
    }	
}
