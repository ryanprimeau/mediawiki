<?php
/**
 *
 *
 * Created on Sep 7, 2006
 *
 * Copyright © 2006 Yuri Astrakhan "<Firstname><Lastname>@gmail.com"
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * This is a base class for all Query modules.
 * It provides some common functionality such as constructing various SQL
 * queries.
 *
 * @ingroup API
 */
abstract class ApiQueryBase extends ApiBase {

	private $mQueryModule, $mDb, $tables, $where, $fields, $options, $join_conds;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param string $paramPrefix
	 */
	public function __construct( ApiQuery $queryModule, $moduleName, $paramPrefix = '' ) {
		parent::__construct( $queryModule->getMain(), $moduleName, $paramPrefix );
		$this->mQueryModule = $queryModule;
		$this->mDb = null;
		$this->resetQueryParams();
	}

	/************************************************************************//**
	 * @name   Methods to implement
	 * @{
	 */

	/**
	 * Get the cache mode for the data generated by this module. Override
	 * this in the module subclass. For possible return values and other
	 * details about cache modes, see ApiMain::setCacheMode()
	 *
	 * Public caching will only be allowed if *all* the modules that supply
	 * data for a given request return a cache mode of public.
	 *
	 * @param array $params
	 * @return string
	 */
	public function getCacheMode( $params ) {
		return 'private';
	}

	/**
	 * Override this method to request extra fields from the pageSet
	 * using $pageSet->requestField('fieldName')
	 *
	 * Note this only makes sense for 'prop' modules, as 'list' and 'meta'
	 * modules should not be using the pageset.
	 *
	 * @param ApiPageSet $pageSet
	 */
	public function requestExtraData( $pageSet ) {
	}

	/**@}*/

	/************************************************************************//**
	 * @name   Data access
	 * @{
	 */

	/**
	 * Get the main Query module
	 * @return ApiQuery
	 */
	public function getQuery() {
		return $this->mQueryModule;
	}

	/**
	 * @see ApiBase::getParent()
	 */
	public function getParent() {
		return $this->getQuery();
	}

	/**
	 * Get the Query database connection (read-only)
	 * @return DatabaseBase
	 */
	protected function getDB() {
		if ( is_null( $this->mDb ) ) {
			$this->mDb = $this->getQuery()->getDB();
		}

		return $this->mDb;
	}

	/**
	 * Selects the query database connection with the given name.
	 * See ApiQuery::getNamedDB() for more information
	 * @param string $name Name to assign to the database connection
	 * @param int $db One of the DB_* constants
	 * @param array $groups Query groups
	 * @return DatabaseBase
	 */
	public function selectNamedDB( $name, $db, $groups ) {
		$this->mDb = $this->getQuery()->getNamedDB( $name, $db, $groups );
		return $this->mDb;
	}

	/**
	 * Get the PageSet object to work on
	 * @return ApiPageSet
	 */
	protected function getPageSet() {
		return $this->getQuery()->getPageSet();
	}

	/**@}*/

	/************************************************************************//**
	 * @name   Querying
	 * @{
	 */

	/**
	 * Blank the internal arrays with query parameters
	 */
	protected function resetQueryParams() {
		$this->tables = [];
		$this->where = [];
		$this->fields = [];
		$this->options = [];
		$this->join_conds = [];
	}

	/**
	 * Add a set of tables to the internal array
	 * @param string|string[] $tables Table name or array of table names
	 * @param string|null $alias Table alias, or null for no alias. Cannot be
	 *  used with multiple tables
	 */
	protected function addTables( $tables, $alias = null ) {
		if ( is_array( $tables ) ) {
			if ( !is_null( $alias ) ) {
				ApiBase::dieDebug( __METHOD__, 'Multiple table aliases not supported' );
			}
			$this->tables = array_merge( $this->tables, $tables );
		} else {
			if ( !is_null( $alias ) ) {
				$this->tables[$alias] = $tables;
			} else {
				$this->tables[] = $tables;
			}
		}
	}

	/**
	 * Add a set of JOIN conditions to the internal array
	 *
	 * JOIN conditions are formatted as array( tablename => array(jointype,
	 * conditions) e.g. array('page' => array('LEFT JOIN',
	 * 'page_id=rev_page')) . conditions may be a string or an
	 * addWhere()-style array
	 * @param array $join_conds JOIN conditions
	 */
	protected function addJoinConds( $join_conds ) {
		if ( !is_array( $join_conds ) ) {
			ApiBase::dieDebug( __METHOD__, 'Join conditions have to be arrays' );
		}
		$this->join_conds = array_merge( $this->join_conds, $join_conds );
	}

	/**
	 * Add a set of fields to select to the internal array
	 * @param array|string $value Field name or array of field names
	 */
	protected function addFields( $value ) {
		if ( is_array( $value ) ) {
			$this->fields = array_merge( $this->fields, $value );
		} else {
			$this->fields[] = $value;
		}
	}

	/**
	 * Same as addFields(), but add the fields only if a condition is met
	 * @param array|string $value See addFields()
	 * @param bool $condition If false, do nothing
	 * @return bool $condition
	 */
	protected function addFieldsIf( $value, $condition ) {
		if ( $condition ) {
			$this->addFields( $value );

			return true;
		}

		return false;
	}

	/**
	 * Add a set of WHERE clauses to the internal array.
	 * Clauses can be formatted as 'foo=bar' or array('foo' => 'bar'),
	 * the latter only works if the value is a constant (i.e. not another field)
	 *
	 * If $value is an empty array, this function does nothing.
	 *
	 * For example, array('foo=bar', 'baz' => 3, 'bla' => 'foo') translates
	 * to "foo=bar AND baz='3' AND bla='foo'"
	 * @param string|array $value
	 */
	protected function addWhere( $value ) {
		if ( is_array( $value ) ) {
			// Sanity check: don't insert empty arrays,
			// Database::makeList() chokes on them
			if ( count( $value ) ) {
				$this->where = array_merge( $this->where, $value );
			}
		} else {
			$this->where[] = $value;
		}
	}

	/**
	 * Same as addWhere(), but add the WHERE clauses only if a condition is met
	 * @param string|array $value
	 * @param bool $condition If false, do nothing
	 * @return bool $condition
	 */
	protected function addWhereIf( $value, $condition ) {
		if ( $condition ) {
			$this->addWhere( $value );

			return true;
		}

		return false;
	}

	/**
	 * Equivalent to addWhere(array($field => $value))
	 * @param string $field Field name
	 * @param string $value Value; ignored if null or empty array;
	 */
	protected function addWhereFld( $field, $value ) {
		// Use count() to its full documented capabilities to simultaneously
		// test for null, empty array or empty countable object
		if ( count( $value ) ) {
			$this->where[$field] = $value;
		}
	}

	/**
	 * Add a WHERE clause corresponding to a range, and an ORDER BY
	 * clause to sort in the right direction
	 * @param string $field Field name
	 * @param string $dir If 'newer', sort in ascending order, otherwise
	 *  sort in descending order
	 * @param string $start Value to start the list at. If $dir == 'newer'
	 *  this is the lower boundary, otherwise it's the upper boundary
	 * @param string $end Value to end the list at. If $dir == 'newer' this
	 *  is the upper boundary, otherwise it's the lower boundary
	 * @param bool $sort If false, don't add an ORDER BY clause
	 */
	protected function addWhereRange( $field, $dir, $start, $end, $sort = true ) {
		$isDirNewer = ( $dir === 'newer' );
		$after = ( $isDirNewer ? '>=' : '<=' );
		$before = ( $isDirNewer ? '<=' : '>=' );
		$db = $this->getDB();

		if ( !is_null( $start ) ) {
			$this->addWhere( $field . $after . $db->addQuotes( $start ) );
		}

		if ( !is_null( $end ) ) {
			$this->addWhere( $field . $before . $db->addQuotes( $end ) );
		}

		if ( $sort ) {
			$order = $field . ( $isDirNewer ? '' : ' DESC' );
			// Append ORDER BY
			$optionOrderBy = isset( $this->options['ORDER BY'] )
				? (array)$this->options['ORDER BY']
				: [];
			$optionOrderBy[] = $order;
			$this->addOption( 'ORDER BY', $optionOrderBy );
		}
	}

	/**
	 * Add a WHERE clause corresponding to a range, similar to addWhereRange,
	 * but converts $start and $end to database timestamps.
	 * @see addWhereRange
	 * @param string $field
	 * @param string $dir
	 * @param string $start
	 * @param string $end
	 * @param bool $sort
	 */
	protected function addTimestampWhereRange( $field, $dir, $start, $end, $sort = true ) {
		$db = $this->getDB();
		$this->addWhereRange( $field, $dir,
			$db->timestampOrNull( $start ), $db->timestampOrNull( $end ), $sort );
	}

	/**
	 * Add an option such as LIMIT or USE INDEX. If an option was set
	 * before, the old value will be overwritten
	 * @param string $name Option name
	 * @param string $value Option value
	 */
	protected function addOption( $name, $value = null ) {
		if ( is_null( $value ) ) {
			$this->options[] = $name;
		} else {
			$this->options[$name] = $value;
		}
	}

	/**
	 * Execute a SELECT query based on the values in the internal arrays
	 * @param string $method Function the query should be attributed to.
	 *  You should usually use __METHOD__ here
	 * @param array $extraQuery Query data to add but not store in the object
	 *  Format is array(
	 *    'tables' => ...,
	 *    'fields' => ...,
	 *    'where' => ...,
	 *    'options' => ...,
	 *    'join_conds' => ...
	 *  )
	 * @return ResultWrapper
	 */
	protected function select( $method, $extraQuery = [] ) {

		$tables = array_merge(
			$this->tables,
			isset( $extraQuery['tables'] ) ? (array)$extraQuery['tables'] : []
		);
		$fields = array_merge(
			$this->fields,
			isset( $extraQuery['fields'] ) ? (array)$extraQuery['fields'] : []
		);
		$where = array_merge(
			$this->where,
			isset( $extraQuery['where'] ) ? (array)$extraQuery['where'] : []
		);
		$options = array_merge(
			$this->options,
			isset( $extraQuery['options'] ) ? (array)$extraQuery['options'] : []
		);
		$join_conds = array_merge(
			$this->join_conds,
			isset( $extraQuery['join_conds'] ) ? (array)$extraQuery['join_conds'] : []
		);

		$res = $this->getDB()->select( $tables, $fields, $where, $method, $options, $join_conds );

		return $res;
	}

	/**
	 * @param string $query
	 * @param string $protocol
	 * @return null|string
	 */
	public function prepareUrlQuerySearchString( $query = null, $protocol = null ) {
		$db = $this->getDB();
		if ( !is_null( $query ) || $query != '' ) {
			if ( is_null( $protocol ) ) {
				$protocol = 'http://';
			}

			$likeQuery = LinkFilter::makeLikeArray( $query, $protocol );
			if ( !$likeQuery ) {
				$this->dieUsage( 'Invalid query', 'bad_query' );
			}

			$likeQuery = LinkFilter::keepOneWildcard( $likeQuery );

			return 'el_index ' . $db->buildLike( $likeQuery );
		} elseif ( !is_null( $protocol ) ) {
			return 'el_index ' . $db->buildLike( "$protocol", $db->anyString() );
		}

		return null;
	}

	/**
	 * Filters hidden users (where the user doesn't have the right to view them)
	 * Also adds relevant block information
	 *
	 * @param bool $showBlockInfo
	 * @return void
	 */
	public function showHiddenUsersAddBlockInfo( $showBlockInfo ) {
		$this->addTables( 'ipblocks' );
		$this->addJoinConds( [
			'ipblocks' => [ 'LEFT JOIN', 'ipb_user=user_id' ],
		] );

		$this->addFields( 'ipb_deleted' );

		if ( $showBlockInfo ) {
			$this->addFields( [
				'ipb_id',
				'ipb_by',
				'ipb_by_text',
				'ipb_reason',
				'ipb_expiry',
				'ipb_timestamp'
			] );
		}

		// Don't show hidden names
		if ( !$this->getUser()->isAllowed( 'hideuser' ) ) {
			$this->addWhere( 'ipb_deleted = 0 OR ipb_deleted IS NULL' );
		}
	}

	/**@}*/

	/************************************************************************//**
	 * @name   Utility methods
	 * @{
	 */

	/**
	 * Add information (title and namespace) about a Title object to a
	 * result array
	 * @param array $arr Result array à la ApiResult
	 * @param Title $title
	 * @param string $prefix Module prefix
	 */
	public static function addTitleInfo( &$arr, $title, $prefix = '' ) {
		$arr[$prefix . 'ns'] = intval( $title->getNamespace() );
		$arr[$prefix . 'title'] = $title->getPrefixedText();
	}

	/**
	 * Add a sub-element under the page element with the given page ID
	 * @param int $pageId Page ID
	 * @param array $data Data array à la ApiResult
	 * @return bool Whether the element fit in the result
	 */
	protected function addPageSubItems( $pageId, $data ) {
		$result = $this->getResult();
		ApiResult::setIndexedTagName( $data, $this->getModulePrefix() );

		return $result->addValue( [ 'query', 'pages', intval( $pageId ) ],
			$this->getModuleName(),
			$data );
	}

	/**
	 * Same as addPageSubItems(), but one element of $data at a time
	 * @param int $pageId Page ID
	 * @param array $item Data array à la ApiResult
	 * @param string $elemname XML element name. If null, getModuleName()
	 *  is used
	 * @return bool Whether the element fit in the result
	 */
	protected function addPageSubItem( $pageId, $item, $elemname = null ) {
		if ( is_null( $elemname ) ) {
			$elemname = $this->getModulePrefix();
		}
		$result = $this->getResult();
		$fit = $result->addValue( [ 'query', 'pages', $pageId,
			$this->getModuleName() ], null, $item );
		if ( !$fit ) {
			return false;
		}
		$result->addIndexedTagName( [ 'query', 'pages', $pageId,
			$this->getModuleName() ], $elemname );

		return true;
	}

	/**
	 * Set a query-continue value
	 * @param string $paramName Parameter name
	 * @param string|array $paramValue Parameter value
	 */
	protected function setContinueEnumParameter( $paramName, $paramValue ) {
		$this->getContinuationManager()->addContinueParam( $this, $paramName, $paramValue );
	}

	/**
	 * Convert an input title or title prefix into a dbkey.
	 *
	 * $namespace should always be specified in order to handle per-namespace
	 * capitalization settings.
	 *
	 * @param string $titlePart Title part
	 * @param int $namespace Namespace of the title
	 * @return string DBkey (no namespace prefix)
	 */
	public function titlePartToKey( $titlePart, $namespace = NS_MAIN ) {
		$t = Title::makeTitleSafe( $namespace, $titlePart . 'x' );
		if ( !$t || $t->hasFragment() ) {
			// Invalid title (e.g. bad chars) or contained a '#'.
			$this->dieUsageMsg( [ 'invalidtitle', $titlePart ] );
		}
		if ( $namespace != $t->getNamespace() || $t->isExternal() ) {
			// This can happen in two cases. First, if you call titlePartToKey with a title part
			// that looks like a namespace, but with $defaultNamespace = NS_MAIN. It would be very
			// difficult to handle such a case. Such cases cannot exist and are therefore treated
			// as invalid user input. The second case is when somebody specifies a title interwiki
			// prefix.
			$this->dieUsageMsg( [ 'invalidtitle', $titlePart ] );
		}

		return substr( $t->getDBkey(), 0, -1 );
	}

	/**
	 * Convert an input title or title prefix into a namespace constant and dbkey.
	 *
	 * @since 1.26
	 * @param string $titlePart Title part
	 * @param int $defaultNamespace Default namespace if none is given
	 * @return array (int, string) Namespace number and DBkey
	 */
	public function prefixedTitlePartToKey( $titlePart, $defaultNamespace = NS_MAIN ) {
		$t = Title::newFromText( $titlePart . 'x', $defaultNamespace );
		if ( !$t || $t->hasFragment() || $t->isExternal() ) {
			// Invalid title (e.g. bad chars) or contained a '#'.
			$this->dieUsageMsg( [ 'invalidtitle', $titlePart ] );
		}

		return [ $t->getNamespace(), substr( $t->getDBkey(), 0, -1 ) ];
	}

	/**
	 * @param string $hash
	 * @return bool
	 */
	public function validateSha1Hash( $hash ) {
		return preg_match( '/^[a-f0-9]{40}$/', $hash );
	}

	/**
	 * @param string $hash
	 * @return bool
	 */
	public function validateSha1Base36Hash( $hash ) {
		return preg_match( '/^[a-z0-9]{31}$/', $hash );
	}

	/**
	 * Check whether the current user has permission to view revision-deleted
	 * fields.
	 * @return bool
	 */
	public function userCanSeeRevDel() {
		return $this->getUser()->isAllowedAny(
			'deletedhistory',
			'deletedtext',
			'suppressrevision',
			'viewsuppressed'
		);
	}

	/**@}*/
}
