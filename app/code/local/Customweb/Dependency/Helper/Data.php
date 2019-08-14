<?php
/**
 ::[Header]::
 * @category	Local
 * @package	Customweb_Dependency
 * @link		http://www.customweb.ch
 */

class Customweb_Dependency_Helper_Data extends Mage_Core_Helper_Abstract
{
	
	
	/**
	 * Traverses the graph provided by $edges starting at node $node and looks for
	 * cycles.
	 * 
	 * @param int $node
	 * @param array $edges
	 * @return boolean True if graph contains no cylces starting at $node
	 */
	public function dfs($node,$edges)
	{
		static $visited = array();
		$noLoop = true;
		if(array_key_exists($node,$visited) && $visited[$node] == 1)
		{
			return false;
		}
		else
		{
			$visited[$node] = 1;
			
			if(isset($edges[$node]))
			{
				foreach($edges[$node] as $nextNode)
				{
					$noLoop = $noLoop && $this->dfs($nextNode,$edges);
				}
			}
		}
		
		$visited[$node] = 0;
		return $noLoop;
	}
	
	
	
	/**
	 * Returns an array of all store ids.
	 * 
	 * @return array List of store ids.
	 */
	public function getAllStoreIds()
	{
		return array_keys(Mage::app()->getStores());
	}
	
	
	
	/**
	 * Stores the mapping from type names to model names.
	 * @var array
	 */
	protected $typeToModel = null;
}
