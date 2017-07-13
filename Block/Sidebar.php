<?php namespace Sebwite\Sidebar\Block;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Framework\Data\Tree\Node as TreeNode;
use Magento\Framework\Data\Tree\Node\Collection as TreeNodeCollection;
use Magento\Framework\View\Element\Template;

/**
 * Class:Sidebar
 * Sebwite\Sidebar\Block
 *
 * @author      Sebwite
 * @package     Sebwite\Sidebar
 * @copyright   Copyright (c) 2015, Sebwite. All rights reserved
 */
class Sidebar extends Template
{
    /**
     * System configuration options
     */
    const XML_PATH_VISIBLE_IN_MENU = 'sebwite_sidebar/general/visible_in_menu';

    /**
     * @var \Magento\Catalog\Helper\Category
     */
    protected $_categoryHelper;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     * @var \Magento\Catalog\Model\Indexer\Category\Flat\State
     */
    protected $categoryFlatConfig;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var array
     */
    protected $_storeCategories = [];

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\TreeFactory
     */
    private $categoryTreeFactory;

    /**
     * @var \Magento\Catalog\Model\Attribute\Config
     */
    private $attributeConfig;

    /** @var \Magento\Store\Model\Store */
    protected $_store;

    /**
     * Sidebar constructor.
     * @param Template\Context                                                $context
     * @param \Magento\Catalog\Helper\Category                                $categoryHelper
     * @param \Magento\Framework\Registry                                     $registry
     * @param \Magento\Catalog\Model\Indexer\Category\Flat\State              $categoryFlatState
     * @param \Magento\Catalog\Model\Attribute\Config                         $attributeConfig
     * @param \Magento\Catalog\Model\ResourceModel\Category\TreeFactory       $categoryTreeFactory
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param array                                                           $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Catalog\Helper\Category $categoryHelper,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryFlatState,
        \Magento\Catalog\Model\Attribute\Config $attributeConfig,
        \Magento\Catalog\Model\ResourceModel\Category\TreeFactory $categoryTreeFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Store\Model\Store $store,
        $data = []
    )
    {
        $this->_categoryHelper = $categoryHelper;
        $this->_coreRegistry = $registry;
        $this->categoryFlatConfig = $categoryFlatState;
        $this->categoryTreeFactory = $categoryTreeFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->attributeConfig = $attributeConfig;
        $this->_store = $store;

        parent::__construct($context, $data);
    }

    /*
    * Get owner name
    * @return string
    */

    /**
     * Get all categories
     *
     * @param int  $recursionLevel
     * @param bool $sorted
     * @param bool $asCollection
     * @param bool $toLoad
     * @return array|CategoryCollection|TreeNodeCollection
     */
    public function getCategories($recursionLevel = 1, $sorted = false, $asCollection = false, $toLoad = true)
    {
        $activeCategory = $this->_coreRegistry->registry('current_category');
        $recursionLevel = ($activeCategory->getLevel()+1);

        $cacheKey = sprintf('%d-%d-%d-%d', $this->getSelectedRootCategory(), $sorted, $asCollection, $toLoad);
        if (isset($this->_storeCategories[$cacheKey])) {
            return $this->_storeCategories[$cacheKey];
        }

        $storeCategories = $this->getCategoryTreeByParent($recursionLevel, $sorted, $asCollection, $toLoad);

        $this->_storeCategories[$cacheKey] = $storeCategories;

        return $storeCategories;
    }

    /**
     * Custom tree caretion to omit 'include_in_menu' filtering
     *
     * @param int  $recursionLevel
     * @param bool $sorted
     * @param bool $asCollection
     * @param bool $toLoad
     * @return CategoryCollection|TreeNodeCollection
     */
    protected function getCategoryTreeByParent($recursionLevel, $sorted, $asCollection, $toLoad)
    {
        //todo calculate recursionLevel (should be the level of current category  + 1 )

        $collection = $this->categoryCollectionFactory->create();
        $collection
            ->setStoreId($this->_storeManager->getStore()->getId())
            ->addAttributeToSelect('name')
            ->addIsActiveFilter();

        $visibleInMenu = $this->_scopeConfig->getValue(self::XML_PATH_VISIBLE_IN_MENU);
        if ($visibleInMenu) {
            $collection->addAttributeToFilter('include_in_menu', true);
        }

        $attributes = $this->attributeConfig->getAttributeNames('catalog_category');
        $collection->addAttributeToSelect($attributes);

        $tree = $this->categoryTreeFactory->create();
        $nodes = $tree->loadNode($this->getSelectedRootCategory())->loadChildren($recursionLevel)->getChildren();

        $tree->addCollectionData($collection, $sorted, [], $toLoad, false);

        if ($asCollection) {
            return $tree->getCollection();
        }

        return $nodes;
    }

    /**
     * getSelectedRootCategory method
     *
     * @return int|mixed
     */
    public function getSelectedRootCategory()
    {
        $activeCategory = $this->_coreRegistry->registry('current_category');

        if (!empty($activeCategory)) {
            foreach ($this->getParentCategories($activeCategory) as $parent) {
                if ($parent->getLevel() == 2) {
                    return $parent->getId();
                }
            }
        }

        return $this->_store->getStoreRootCategoryId();
    }

    /**
     * @param        $category
     * @param string $html
     * @param int    $level
     *
     * @return string
     */
    public function getChildCategoryView($category, $html = '', $level = 1)
    {
        // Check if category has children
        if ($category->hasChildren()) {

            $childCategories = $this->getSubcategories($category);

            if (count($childCategories) > 0) {

                $html .= '<ul class="o-list o-list--unstyled">';

                // Loop through children categories
                foreach ($childCategories as $childCategory) {

                    $html .= '<li class="level' . $level . ($this->isActive($childCategory) ? ' active' : '') . '">';
                    $html .= '<a href="' . $this->getCategoryUrl($childCategory) . '" title="' . $childCategory->getName() . '" class="' . ($this->isActive($childCategory) ? 'active' : '') . '">' . $childCategory->getName() . '</a>';

                    if ($childCategory->hasChildren()) {
                        if ($this->isActive($childCategory)) {
                            $html .= '<span class="expanded"><i class="fa fa-minus"></i></span>';
                        } else {
                            $html .= '<span class="expand"><i class="fa fa-plus"></i></span>';
                        }
                    }

                    if ($childCategory->hasChildren()) {
                        $html .= $this->getChildCategoryView($childCategory, '', ($level + 1));
                    }

                    $html .= '</li>';
                }
                $html .= '</ul>';
            }
        }

        return $html;
    }

    /**
     * Retrieve subcategories
     *
     * @param TreeNode|Category $category
     *
     * @return array
     */
    public function getSubcategories($category)
    {
        if ($this->categoryFlatConfig->isFlatEnabled() && $category->getUseFlatResource()) {
            return (array)$category->getChildrenNodes();
        }

        return $category->getChildren();
    }

    /**
     * Get current category
     *
     * @param TreeNode|Category $category
     *
     * @return bool
     */
    public function isActive($category)
    {
        $activeCategory = $this->_coreRegistry->registry('current_category');
        $activeProduct = $this->_coreRegistry->registry('current_product');

        if (!$activeCategory) {

            // Check if we're on a product page
            if ($activeProduct !== null) {
                return in_array($category->getId(), $activeProduct->getCategoryIds());
            }

            return false;
        }

        // Check if this is the active category
        if ($this->categoryFlatConfig->isFlatEnabled() && $category->getUseFlatResource() AND
            $category->getId() == $activeCategory->getId()
        ) {
            return true;
        }

        // Check if a subcategory of this category is active
        $activeCategoryTree = explode('/', $activeCategory->getPath());
        if (in_array($category->getId(), $activeCategoryTree)) {
            return true;
        }

        // Fallback - If Flat categories is not enabled the active category does not give an id
        return (($category->getName() == $activeCategory->getName()) ? true : false);
    }

    /**
     * Return Category Id for $category object
     *
     * @param TreeNode|Category $category
     *
     * @return string
     */
    public function getCategoryUrl($category)
    {
        return $this->_categoryHelper->getCategoryUrl($category);
    }


    /**
     * @param $category
     * @return \Magento\Framework\DataObject[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getParentCategories($category)
    {
        $pathIds = array_reverse(explode(',', $category->getPathInStore()));
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $categories */
        $categories = $this->categoryCollectionFactory->create();

        return $categories
            ->setStore(
                $this->_storeManager->getStore()
            )
            ->addAttributeToSelect('entity_id','name', 'level')
            ->addFieldToFilter('entity_id', ['in' => $pathIds])
            ->addFieldToFilter('is_active', 1)
            ->load()
            ->getItems();
    }
}