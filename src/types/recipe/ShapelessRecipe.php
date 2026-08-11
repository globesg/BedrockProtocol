<?php

declare(strict_types=1);
namespace pocketmine\network\mcpe\protocol\types\recipe;
use pmmp\encoding\ByteBufferReader;use pmmp\encoding\ByteBufferWriter;use pmmp\encoding\VarInt;use pocketmine\network\mcpe\protocol\ProtocolInfo;use pocketmine\network\mcpe\protocol\serializer\CommonTypes;use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;use Ramsey\Uuid\UuidInterface;use function count;
final class ShapelessRecipe extends RecipeWithTypeId{
	/** @param list<RecipeIngredient> $inputs @param list<ItemStack> $outputs */
	public function __construct(int $typeId,private string $recipeId,private array $inputs,private array $outputs,private UuidInterface $uuid,private string $blockName,private int $priority,private ?RecipeUnlockingRequirement $unlockingRequirement,private int $recipeNetId){parent::__construct($typeId);}
	public function getRecipeId():string{return $this->recipeId;} public function getInputs():array{return $this->inputs;} public function getOutputs():array{return $this->outputs;} public function getUuid():UuidInterface{return $this->uuid;} public function getBlockName():string{return $this->blockName;} public function getPriority():int{return $this->priority;} public function getUnlockingRequirement():?RecipeUnlockingRequirement{return $this->unlockingRequirement;} public function getRecipeNetId():int{return $this->recipeNetId;}
	public static function decode(int $recipeType,ByteBufferReader $in,int $protocolId):self{
		$current=$protocolId>=ProtocolInfo::PROTOCOL_1_26_40;$id=CommonTypes::getString($in);$input=[];for($i=0,$c=VarInt::readUnsignedInt($in);$i<$c;++$i){$input[]=$current?RecipeIngredient::read12640($in):CommonTypes::getRecipeIngredient($in);} $output=[];for($i=0,$c=VarInt::readUnsignedInt($in);$i<$c;++$i){$output[]=$current?CommonTypes::getItemStackWithoutStackId12640($in):CommonTypes::getItemStackWithoutStackId($in);} $uuid=CommonTypes::getUUID($in);$block=CommonTypes::getString($in);$priority=VarInt::readSignedInt($in);
		if($current){$unlock=CommonTypes::getBool($in)?RecipeUnlockingRequirement::read12640($in):null;$net=VarInt::readSignedInt($in);}else{$unlock=$protocolId>=ProtocolInfo::PROTOCOL_1_21_0?RecipeUnlockingRequirement::readLegacy($in):new RecipeUnlockingRequirement(null);$net=CommonTypes::readRecipeNetId($in);} return new self($recipeType,$id,$input,$output,$uuid,$block,$priority,$unlock,$net);
	}
	public function encode(ByteBufferWriter $out,int $protocolId):void{
		$current=$protocolId>=ProtocolInfo::PROTOCOL_1_26_40;CommonTypes::putString($out,$this->recipeId);VarInt::writeUnsignedInt($out,count($this->inputs));foreach($this->inputs as $item){$current?$item->write12640($out):CommonTypes::putRecipeIngredient($out,$item);} VarInt::writeUnsignedInt($out,count($this->outputs));foreach($this->outputs as $item){$current?CommonTypes::putItemStackWithoutStackId12640($out,$item):CommonTypes::putItemStackWithoutStackId($out,$item);}CommonTypes::putUUID($out,$this->uuid);CommonTypes::putString($out,$this->blockName);VarInt::writeSignedInt($out,$this->priority);
		if($current){CommonTypes::putBool($out,$this->unlockingRequirement!==null);$this->unlockingRequirement?->write12640($out);VarInt::writeSignedInt($out,$this->recipeNetId);}else{if($protocolId>=ProtocolInfo::PROTOCOL_1_21_0){($this->unlockingRequirement??new RecipeUnlockingRequirement(null))->writeLegacy($out);}CommonTypes::writeRecipeNetId($out,$this->recipeNetId);}
	}
}
