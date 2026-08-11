<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types\recipe;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use Ramsey\Uuid\UuidInterface;
use function array_chunk;
use function count;
use function max;

final class ShapedRecipe extends RecipeWithTypeId{
	private string $blockName;
	/** @param list<list<RecipeIngredient>> $input @param list<ItemStack> $output */
	public function __construct(int $typeId, private string $recipeId, private array $input, private array $output, private UuidInterface $uuid, string $blockType, private int $priority, private bool $symmetric, private ?RecipeUnlockingRequirement $unlockingRequirement, private int $recipeNetId){
		parent::__construct($typeId);
		$rows=count($input);
		if($rows < 1 || $rows > 3){ throw new \InvalidArgumentException("Expected 1, 2 or 3 input rows"); }
		$columns=null;
		foreach($input as $rowNumber=>$row){
			if($columns === null){ $columns=count($row); }elseif(count($row) !== $columns){ throw new \InvalidArgumentException("Expected each row to be $columns columns, but have " . count($row) . " in row $rowNumber"); }
		}
		$this->blockName=$blockType;
	}
	public function getRecipeId():string{return $this->recipeId;}
	public function getWidth():int{return count($this->input[0]);}
	public function getHeight():int{return count($this->input);}
	/** @return list<list<RecipeIngredient>> */ public function getInput():array{return $this->input;}
	/** @return list<ItemStack> */ public function getOutput():array{return $this->output;}
	public function getUuid():UuidInterface{return $this->uuid;}
	public function getBlockName():string{return $this->blockName;}
	public function getPriority():int{return $this->priority;}
	public function isSymmetric():bool{return $this->symmetric;}
	public function getUnlockingRequirement():?RecipeUnlockingRequirement{return $this->unlockingRequirement;}
	public function getRecipeNetId():int{return $this->recipeNetId;}

	public static function decode(int $recipeType, ByteBufferReader $in, int $protocolId) : self{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){ return self::decode12640($recipeType, $in); }
		$recipeId=CommonTypes::getString($in); $width=VarInt::readSignedInt($in); $height=VarInt::readSignedInt($in); $input=[];
		for($row=0;$row<$height;++$row){ for($column=0;$column<$width;++$column){ $input[$row][$column]=CommonTypes::getRecipeIngredient($in); } }
		$output=[]; for($k=0,$c=VarInt::readUnsignedInt($in);$k<$c;++$k){$output[]=CommonTypes::getItemStackWithoutStackId($in);}
		$uuid=CommonTypes::getUUID($in); $block=CommonTypes::getString($in); $priority=VarInt::readSignedInt($in); $symmetric=true; $unlocking=new RecipeUnlockingRequirement(null);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_20_80){ $symmetric=CommonTypes::getBool($in); if($protocolId >= ProtocolInfo::PROTOCOL_1_21_0){$unlocking=RecipeUnlockingRequirement::readLegacy($in);} }
		return new self($recipeType,$recipeId,$input,$output,$uuid,$block,$priority,$symmetric,$unlocking,CommonTypes::readRecipeNetId($in));
	}
	private static function decode12640(int $recipeType, ByteBufferReader $in):self{
		$recipeId=CommonTypes::getString($in); $width=VarInt::readSignedInt($in); $height=VarInt::readSignedInt($in); $ingredients=[];
		for($i=0,$c=VarInt::readUnsignedInt($in);$i<$c;++$i){$ingredients[]=RecipeIngredient::read12640($in);} $input=array_chunk($ingredients,max(1,$width));
		$output=[]; for($i=0,$c=VarInt::readUnsignedInt($in);$i<$c;++$i){$output[]=CommonTypes::getItemStackWithoutStackId12640($in);}
		$uuid=CommonTypes::getUUID($in); $block=CommonTypes::getString($in); $priority=VarInt::readSignedInt($in); $symmetric=CommonTypes::getBool($in);
		$unlocking=CommonTypes::getBool($in)?RecipeUnlockingRequirement::read12640($in):null;
		return new self($recipeType,$recipeId,$input,$output,$uuid,$block,$priority,$symmetric,$unlocking,VarInt::readSignedInt($in));
	}
	public function encode(ByteBufferWriter $out, int $protocolId):void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){$this->encode12640($out);return;}
		CommonTypes::putString($out,$this->recipeId); VarInt::writeSignedInt($out,$this->getWidth()); VarInt::writeSignedInt($out,$this->getHeight());
		foreach($this->input as $row){foreach($row as $ingredient){CommonTypes::putRecipeIngredient($out,$ingredient);}}
		VarInt::writeUnsignedInt($out,count($this->output)); foreach($this->output as $item){CommonTypes::putItemStackWithoutStackId($out,$item);}
		CommonTypes::putUUID($out,$this->uuid); CommonTypes::putString($out,$this->blockName); VarInt::writeSignedInt($out,$this->priority);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_20_80){CommonTypes::putBool($out,$this->symmetric);if($protocolId >= ProtocolInfo::PROTOCOL_1_21_0){($this->unlockingRequirement??new RecipeUnlockingRequirement(null))->writeLegacy($out);}}
		CommonTypes::writeRecipeNetId($out,$this->recipeNetId);
	}
	private function encode12640(ByteBufferWriter $out):void{
		CommonTypes::putString($out,$this->recipeId); VarInt::writeSignedInt($out,$this->getWidth()); VarInt::writeSignedInt($out,$this->getHeight()); VarInt::writeUnsignedInt($out,$this->getWidth()*$this->getHeight());
		foreach($this->input as $row){foreach($row as $ingredient){$ingredient->write12640($out);}}
		VarInt::writeUnsignedInt($out,count($this->output));foreach($this->output as $item){CommonTypes::putItemStackWithoutStackId12640($out,$item);}
		CommonTypes::putUUID($out,$this->uuid);CommonTypes::putString($out,$this->blockName);VarInt::writeSignedInt($out,$this->priority);CommonTypes::putBool($out,$this->symmetric);
		CommonTypes::putBool($out,$this->unlockingRequirement!==null);$this->unlockingRequirement?->write12640($out);VarInt::writeSignedInt($out,$this->recipeNetId);
	}
}
