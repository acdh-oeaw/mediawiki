<?php

namespace Wikimedia\Diff;

/**
 * @author Addshore
 *
 * @group Diff
 */
class ArrayDiffFormatterTest extends \MediaWikiUnitTestCase {

	/**
	 * @param Diff $input
	 * @param array $expectedOutput
	 * @dataProvider provideTestFormat
	 * @covers \Wikimedia\Diff\ArrayDiffFormatter::format
	 */
	public function testFormat( Diff $input, array $expectedOutput ) {
		$instance = new ArrayDiffFormatter();
		$output = $instance->format( $input );
		$this->assertSame( $expectedOutput, $output );
	}

	private function getMockDiff( array $edits ) {
		$diff = $this->createMock( Diff::class );
		$diff->method( 'getEdits' )
			->willReturn( $edits );
		return $diff;
	}

	private function getMockDiffOp( string $type, $orig = [], array $closing = [] ) {
		$diffOp = $this->createMock( DiffOp::class );
		$diffOp->method( 'getType' )
			->willReturn( $type );
		$diffOp->method( 'getOrig' )
			->willReturn( $orig );
		if ( $type === 'change' ) {
			$diffOp->method( 'getClosing' )
				->with( $this->isType( 'integer' ) )
				->willReturn( 'mockLine' );
		} else {
			$diffOp->method( 'getClosing' )
				->willReturn( $closing );
		}
		return $diffOp;
	}

	public function provideTestFormat() {
		$emptyArrayTestCases = [
			$this->getMockDiff( [] ),
			$this->getMockDiff( [ $this->getMockDiffOp( 'add' ) ] ),
			$this->getMockDiff( [ $this->getMockDiffOp( 'delete' ) ] ),
			$this->getMockDiff( [ $this->getMockDiffOp( 'change' ) ] ),
			$this->getMockDiff( [ $this->getMockDiffOp( 'copy' ) ] ),
			$this->getMockDiff( [ $this->getMockDiffOp( 'FOOBARBAZ' ) ] ),
			$this->getMockDiff( [ $this->getMockDiffOp( 'add', 'line' ) ] ),
			$this->getMockDiff( [ $this->getMockDiffOp( 'delete', [], [ 'line' ] ) ] ),
			$this->getMockDiff( [ $this->getMockDiffOp( 'copy', [], [ 'line' ] ) ] ),
		];
		foreach ( $emptyArrayTestCases as $testCase ) {
			yield [ $testCase, [] ];
		}

		yield [
			$this->getMockDiff( [ $this->getMockDiffOp( 'add', [], [ 'a1' ] ) ] ),
			[ [ 'action' => 'add', 'new' => 'a1', 'newline' => 1 ] ],
		];
		yield [
			$this->getMockDiff( [ $this->getMockDiffOp( 'add', [], [ 'a1', 'a2' ] ) ] ),
			[
				[ 'action' => 'add', 'new' => 'a1', 'newline' => 1 ],
				[ 'action' => 'add', 'new' => 'a2', 'newline' => 2 ],
			],
		];
		yield [
			$this->getMockDiff( [ $this->getMockDiffOp( 'delete', [ 'd1' ] ) ] ),
			[ [ 'action' => 'delete', 'old' => 'd1', 'oldline' => 1 ] ],
		];
		yield [
			$this->getMockDiff( [ $this->getMockDiffOp( 'delete', [ 'd1', 'd2' ] ) ] ),
			[
				[ 'action' => 'delete', 'old' => 'd1', 'oldline' => 1 ],
				[ 'action' => 'delete', 'old' => 'd2', 'oldline' => 2 ],
			],
		];
		yield [
			$this->getMockDiff( [ $this->getMockDiffOp( 'change', [ 'd1' ], [ 'a1' ] ) ] ),
			[ [
				'action' => 'change',
				'old' => 'd1',
				'new' => 'mockLine',
				'oldline' => 1,
				'newline' => 1,
			] ],
		];
		yield [
			$this->getMockDiff( [ $this->getMockDiffOp(
				'change',
				[ 'd1', 'd2' ],
				[ 'a1', 'a2' ]
			) ] ),
			[
				[
					'action' => 'change',
					'old' => 'd1',
					'new' => 'mockLine',
					'oldline' => 1,
					'newline' => 1,
				],
				[
					'action' => 'change',
					'old' => 'd2',
					'new' => 'mockLine',
					'oldline' => 2,
					'newline' => 2,
				],
			],
		];
	}

}
