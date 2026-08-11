<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types\recipe;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function count;

final class RecipeUnlockingRequirement{
	public const CONTEXT_NONE = 0;
	public const CONTEXT_ALWAYS_UNLOCKED = 1;
	public const CONTEXT_PLAYER_IN_WATER = 2;
	public const CONTEXT_PLAYER_HAS_MANY_ITEMS = 3;

	/** @param RecipeIngredient[]|null $unlockingIngredients */
	public function __construct(int|array|null $contextOrIngredients, ?array $unlockingIngredients = null){
		if(is_int($contextOrIngredients)){
			$this->unlockingContext = $contextOrIngredients;
			$this->unlockingIngredients = $unlockingIngredients;
		}else{
			$this->unlockingContext = $contextOrIngredients === null ? self::CONTEXT_ALWAYS_UNLOCKED : self::CONTEXT_NONE;
			$this->unlockingIngredients = $contextOrIngredients;
		}
	}
	private int $unlockingContext;
	/** @var RecipeIngredient[]|null */
	private ?array $unlockingIngredients;
	public function getUnlockingContext() : int{ return $this->unlockingContext; }
	/** @return RecipeIngredient[]|null */
	public function getUnlockingIngredients() : ?array{ return $this->unlockingIngredients; }

	public static function readLegacy(ByteBufferReader $in) : self{
		$ingredients = null;
		if(!CommonTypes::getBool($in)){
			$ingredients = [];
			for($i=0,$count=VarInt::readUnsignedInt($in);$i<$count;$i++){ $ingredients[] = CommonTypes::getRecipeIngredient($in); }
		}
		return new self($ingredients);
	}
	public function writeLegacy(ByteBufferWriter $out) : void{
		CommonTypes::putBool($out, $this->unlockingIngredients === null);
		if($this->unlockingIngredients !== null){
			VarInt::writeUnsignedInt($out, count($this->unlockingIngredients));
			foreach($this->unlockingIngredients as $ingredient){ CommonTypes::putRecipeIngredient($out, $ingredient); }
		}
	}
	public static function read12640(ByteBufferReader $in) : self{
		$context = VarInt::readSignedInt($in);
		$ingredients = null;
		if(CommonTypes::getBool($in)){
			$ingredients=[];
			for($i=0,$count=VarInt::readUnsignedInt($in);$i<$count;$i++){ $ingredients[] = RecipeIngredient::read12640($in); }
		}
		return new self($context, $ingredients);
	}
	public function write12640(ByteBufferWriter $out) : void{
		VarInt::writeSignedInt($out, $this->unlockingContext);
		CommonTypes::putBool($out, $this->unlockingIngredients !== null);
		if($this->unlockingIngredients !== null){
			VarInt::writeUnsignedInt($out, count($this->unlockingIngredients));
			foreach($this->unlockingIngredients as $ingredient){ $ingredient->write12640($out); }
		}
	}
	public static function read(ByteBufferReader $in) : self{ return self::readLegacy($in); }
	public function write(ByteBufferWriter $out) : void{ $this->writeLegacy($out); }
}
