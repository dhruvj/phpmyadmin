<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Tests for Node class
 *
 * @package PhpMyAdmin-test
 */

require_once 'libraries/navigation/NodeFactory.class.php';
require_once 'libraries/Util.class.php';
require_once 'libraries/Theme.class.php';
require_once 'libraries/database_interface.inc.php';

/**
 * Tests for Node class
 *
 * @package PhpMyAdmin-test
 */
class Node_Test extends PHPUnit_Framework_TestCase
{
    /**
     * SetUp for test cases
     * 
     * @return void
     */
    public function setup()
    {
        $GLOBALS['server'] = 0;
        $GLOBALS['token'] = 'token';
        $_SESSION['PMA_Theme'] = PMA_Theme::load('./themes/pmahomme');
    }

    /**
     * SetUp for AddNode
     *
     * @return void
     */
    public function testAddNode()
    {
        $parent = PMA_NodeFactory::getInstance('Node', 'parent');
        $child = PMA_NodeFactory::getInstance('Node', 'child');
        $parent->addChild($child);
        $this->assertEquals(
            $parent->getChild($child->name),
            $child
        );
        $this->assertEquals(
            $parent->getChild($child->real_name, true),
            $child
        );
    }

    /**
     * SetUp for getChild
     *
     * @return void
     */
    public function testGetChildError()
    {
        $parent = PMA_NodeFactory::getInstance('Node', 'parent');
        $this->assertEquals(
            $parent->getChild("foo"),
            false
        );
        $this->assertEquals(
            $parent->getChild("foo", true),
            false
        );
    }

    /**
     * SetUp for getChild
     *
     * @return void
     */
    public function testRemoveNode()
    {
        $parent = PMA_NodeFactory::getInstance('Node', 'parent');
        $child = PMA_NodeFactory::getInstance('Node', 'child');
        $parent->addChild($child);
        $this->assertEquals(
            $parent->getChild($child->name),
            $child
        );
        $parent->removeChild($child->name);
        $this->assertEquals(
            $parent->getChild($child->name),
            false
        );
    }

    /**
     * SetUp for hasChildren
     *
     * @return void
     */
    public function testNodeHasChildren()
    {
        $parent = PMA_NodeFactory::getInstance();
        $empty_container = PMA_NodeFactory::getInstance(
            'Node', 'empty', Node::CONTAINER
        );
        $child = PMA_NodeFactory::getInstance();
        // test with no children
        $this->assertEquals(
            $parent->hasChildren(true),
            false
        );
        $this->assertEquals(
            $parent->hasChildren(false),
            false
        );
        // test with an empty container
        $parent->addChild($empty_container);
        $this->assertEquals(
            $parent->hasChildren(true),
            true
        );
        $this->assertEquals(
            $parent->hasChildren(false),
            false
        );
        // test with a real child
        $parent->addChild($child);
        $this->assertEquals(
            $parent->hasChildren(true),
            true
        );
        $this->assertEquals(
            $parent->hasChildren(false),
            true
        );
    }

    /**
     * SetUp for numChildren
     *
     * @return void
     */
    public function testNumChildren()
    {
        // start with root node only
        $parent = PMA_NodeFactory::getInstance();
        $this->assertEquals($parent->numChildren(), 0);
        // add a child
        $child = PMA_NodeFactory::getInstance();
        $parent->addChild($child);
        $this->assertEquals($parent->numChildren(), 1);
        // add a direct grandchild, this one doesn't count as
        // it's not enclosed in a CONTAINER
        $child->addChild(PMA_NodeFactory::getInstance());
        $this->assertEquals($parent->numChildren(), 1);
        // add a container, this one doesn't count wither
        $container = PMA_NodeFactory::getInstance(
            'Node', 'default', Node::CONTAINER
        );
        $parent->addChild($container);
        $this->assertEquals($parent->numChildren(), 1);
        // add a grandchild to container, this one counts
        $container->addChild(PMA_NodeFactory::getInstance());
        $this->assertEquals($parent->numChildren(), 2);
        // add another grandchild to container, this one counts
        $container->addChild(PMA_NodeFactory::getInstance());
        $this->assertEquals($parent->numChildren(), 3);
    }

    /**
     * SetUp for parents
     *
     * @return void
     */
    public function testParents()
    {
        $parent = PMA_NodeFactory::getInstance();
        $this->assertEquals($parent->parents(), array()); // exclude self
        $this->assertEquals($parent->parents(true), array($parent)); // include self

        $child = PMA_NodeFactory::getInstance();
        $parent->addChild($child);

        $this->assertEquals($child->parents(), array($parent)); // exclude self
        $this->assertEquals(
            $child->parents(true),
            array($child, $parent)
        ); // include self
    }

    /**
     * SetUp for realParent
     *
     * @return void
     */
    public function testRealParent()
    {
        $parent = PMA_NodeFactory::getInstance();
        $this->assertEquals($parent->realParent(), false);

        $child = PMA_NodeFactory::getInstance();
        $parent->addChild($child);
        $this->assertEquals($child->realParent(), $parent);
    }

    /**
     * Tests whether Node->hasSiblings() method returns false
     * when the node does not have any siblings.
     *
     * @return void
     * @test
     */
    public function testHasSiblingsWithNoSiblings()
    {
        $parent = PMA_NodeFactory::getInstance();
        $child = PMA_NodeFactory::getInstance();
        $parent->addChild($child);
        $this->assertEquals(false, $child->hasSiblings());
    }

    /**
     * Tests whether Node->hasSiblings() method returns true
     * when it actually has siblings.
     *
     * @return void
     * @test
     */
    public function testHasSiblingsWithSiblings()
    {
        $parent = PMA_NodeFactory::getInstance();
        $firstChild = PMA_NodeFactory::getInstance();
        $parent->addChild($firstChild);
        $secondChild = PMA_NodeFactory::getInstance();
        $parent->addChild($secondChild);
        // Normal case; two Node:NODE type siblings
        $this->assertEquals(true, $firstChild->hasSiblings());

        $parent = PMA_NodeFactory::getInstance();
        $firstChild = PMA_NodeFactory::getInstance();
        $parent->addChild($firstChild);
        $secondChild = PMA_NodeFactory::getInstance(
            'Node', 'default', Node::CONTAINER
        );
        $parent->addChild($secondChild);
        // Empty Node::CONTAINER type node should not be considered in hasSiblings()
        $this->assertEquals(false, $firstChild->hasSiblings());

        $grandChild = PMA_NodeFactory::getInstance();
        $secondChild->addChild($grandChild);
        // Node::CONTAINER type nodes with children are counted for hasSiblings()
        $this->assertEquals(true, $firstChild->hasSiblings());
    }

    /**
     * It is expected that Node->hasSiblings() method always return true
     * for Nodes that are 3 levels deep (columns and indexes).
     *
     * @return void
     * @test
     */
    public function testHasSiblingsForNodesAtLevelThree()
    {
        $parent = PMA_NodeFactory::getInstance();
        $child = PMA_NodeFactory::getInstance();
        $parent->addChild($child);
        $grandChild = PMA_NodeFactory::getInstance();
        $child->addChild($grandChild);
        $greatGrandChild = PMA_NodeFactory::getInstance();
        $grandChild->addChild($greatGrandChild);

        // Should return false for node that are two levels deeps
        $this->assertEquals(false, $grandChild->hasSiblings());
        // Should return true for node that are three levels deeps
        $this->assertEquals(true, $greatGrandChild->hasSiblings());
    }

    /**
     * Tests private method _getWhereClause()
     *
     * @return void
     * @test
     */
    public function testGetWhereClause()
    {
        $method = new ReflectionMethod(
            'Node', '_getWhereClause'
        );
        $method->setAccessible(true);

        // Vanilla case
        $node = PMA_NodeFactory::getInstance();
        $this->assertEquals(
            "WHERE TRUE ", $method->invoke($node)
        );

        // When a schema names is passed as search clause
        $this->assertEquals(
            "WHERE TRUE AND `SCHEMA_NAME` LIKE '%schemaName%' ",
            $method->invoke($node, 'schemaName')
        );

        if (! isset($GLOBALS['cfg'])) {
            $GLOBALS['cfg'] = array();
        }
        if (! isset($GLOBALS['cfg']['Server'])) {
            $GLOBALS['cfg']['Server'] = array();
        }

        // When hide_db regular expression is present
        $GLOBALS['cfg']['Server']['hide_db'] = 'regexpHideDb';
        $this->assertEquals(
            "WHERE TRUE AND `SCHEMA_NAME` NOT REGEXP 'regexpHideDb' ",
            $method->invoke($node)
        );
        unset($GLOBALS['cfg']['Server']['hide_db']);

        // When only_db directive is present and it's a single db
        $GLOBALS['cfg']['Server']['only_db'] = 'stringOnlyDb';
        $this->assertEquals(
            "WHERE TRUE AND ( `SCHEMA_NAME` LIKE 'stringOnlyDb' )",
            $method->invoke($node)
        );
        unset($GLOBALS['cfg']['Server']['only_db']);

        // When only_db directive is present and it's an array of dbs
        $GLOBALS['cfg']['Server']['only_db'] = array('onlyDbOne', 'onlyDbTwo');
        $this->assertEquals(
            "WHERE TRUE AND ( `SCHEMA_NAME` LIKE 'onlyDbOne' "
            . "OR `SCHEMA_NAME` LIKE 'onlyDbTwo' )",
            $method->invoke($node)
        );
        unset($GLOBALS['cfg']['Server']['only_db']);
    }

    /**
     * Tests getData() method
     *
     * @return void
     * @test
     */
    public function testGetData()
    {
        $pos = 10;
        $limit = 20;
        if (! isset($GLOBALS['cfg'])) {
            $GLOBALS['cfg'] = array();
        }
        $GLOBALS['cfg']['MaxNavigationItems'] = $limit;

        $expectedSql  = "SELECT `SCHEMA_NAME` ";
        $expectedSql .= "FROM `INFORMATION_SCHEMA`.`SCHEMATA` ";
        $expectedSql .= "WHERE TRUE ";
        $expectedSql .= "ORDER BY `SCHEMA_NAME` ASC ";
        $expectedSql .= "LIMIT $pos, $limit";

        // It would have been better to mock _getWhereClause method
        // but stangely, mocking private methods is not supported in PHPUnit
        $node = PMA_NodeFactory::getInstance();

        $dbi = $this->getMockBuilder('PMA_DatabaseInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $dbi->expects($this->once())
            ->method('fetchResult')
            ->with($expectedSql);
        $GLOBALS['dbi'] = $dbi;
        $node->getData('', $pos);
    }

    /**
     * Tests the getPresence method when DisableIS is false
     *
     * @return void
     * @test
     */
    public function testGetPresenceWithEnabledIS()
    {
        if (! isset($GLOBALS['cfg'])) {
            $GLOBALS['cfg'] = array();
        }
        if (! isset($GLOBALS['cfg']['Servers'])) {
            $GLOBALS['cfg']['Servers'] = array();
        }
        if (! isset($GLOBALS['cfg']['Servers'][0])) {
            $GLOBALS['cfg']['Servers'][0] = array();
        }
        $GLOBALS['cfg']['Servers'][0]['DisableIS'] = false;

        $query  = "SELECT COUNT(*) ";
        $query .= "FROM `INFORMATION_SCHEMA`.`SCHEMATA` ";
        $query .= "WHERE TRUE ";

        // It would have been better to mock _getWhereClause method
        // but stangely, mocking private methods is not supported in PHPUnit
        $node = PMA_NodeFactory::getInstance();

        $dbi = $this->getMockBuilder('PMA_DatabaseInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $dbi->expects($this->once())
            ->method('fetchValue')
            ->with($query);
        $GLOBALS['dbi'] = $dbi;
        $node->getPresence();
    }

    /**
     * Tests the getPresence method when DisableIS is true
     *
     * @return void
     * @test
     */
    public function testGetPresenceWithDisabledIS()
    {
        if (! isset($GLOBALS['cfg'])) {
            $GLOBALS['cfg'] = array();
        }
        if (! isset($GLOBALS['cfg']['Servers'])) {
            $GLOBALS['cfg']['Servers'] = array();
        }
        if (! isset($GLOBALS['cfg']['Servers'][0])) {
            $GLOBALS['cfg']['Servers'][0] = array();
        }
        $GLOBALS['cfg']['Servers'][0]['DisableIS'] = true;

        $node = PMA_NodeFactory::getInstance();

        // test with no search clause
        $dbi = $this->getMockBuilder('PMA_DatabaseInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $dbi->expects($this->once())
            ->method('tryQuery')
            ->with("SHOW DATABASES ");
        $GLOBALS['dbi'] = $dbi;
        $node->getPresence();

        // test with a search clause
        $dbi = $this->getMockBuilder('PMA_DatabaseInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $dbi->expects($this->once())
            ->method('tryQuery')
            ->with("SHOW DATABASES LIKE '%dbname%' ");
        $GLOBALS['dbi'] = $dbi;
        $node->getPresence('', 'dbname');
    }
}
?>
