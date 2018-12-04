<?php

namespace Mvc\Model;

use \Mvc\Assert;


/**
 * Décrit un type de modèle.
 */
class Schema
{
	protected $name;
	protected $properties = [];
	protected $primaryKey = null;


	/**
	 * Constructeur.
	 */
	public function __construct(string $name, array $definitions=[])
	{
		$this->name = $name;
		$this->addPropertiesFromDef($definitions);
	}


	/**
	 * Interne: renvoie le nom auto-généré à partir d'un nom de classe.
	 *
	 * @param string $class_name
	 *
	 * @return string
	 */
	private static function getSchemaName(string $class_name): string
	{
		$split = explode('\\', $class_name);
		return strtolower($split[count($split)-1]);
	}


	/**
	 * Renvoie un nouveau schéma à partir d'une classe annotée.
	 *
	 * @param string $class_name
	 *
	 * @return Schema
	 */
	public static function getSchemaFromEntity(string $class_name): Schema
	{
		$schema = new Schema(self::getSchemaName($class_name));
		$reflect_class = new \ReflectionClass($class_name);
		$properties = $reflect_class->getProperties(\ReflectionProperty::IS_PUBLIC);
		foreach ($properties as $property_refl) {
			$property = SchemaProperty::createFromClassProperty($property_refl, $schema);
			if (!is_null($property)) {
				$schema->addProperty($property);
			}
		}
		return $schema;
	}


	/**
	 * Représentation chainée.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->name;
	}
	


	/**
	 * Ajoute une propriété dans la définition du schéma.
	 *
	 * @param string $name       Nom de la nouvelle propriété
	 * @param array $definition  Définition
	 *
	 * @return Schema  Pour chainage
	 */
	public function addProperty(SchemaProperty $property): Schema
	{
		array_push($this->properties, $property);

		// Définition éventuelle de la propriété id:
		if ($property->isPrimaryKey()) {
			Assert::mustBeNull($this->primaryKey,
				"Error in schema construction ('{$this->name}'): multiple primary keys are not supported"
			);
			$this->primaryKey = $property;
		}

		return $this;
	}


	/**
	 * Ajoute une propriété dans la définition du schéma.
	 *
	 * @param string $name       Nom de la nouvelle propriété
	 * @param array $definition  Définition
	 *
	 * @return Schema  Pour chainage
	 */
	public function addPropertyFromDef(string $name, array $definition): Schema
	{
		$this->addProperty(new SchemaProperty($this, $name, $definition));
		return $this;
	}


	/**
	 * Ajoute plusieurs propriétés à la fois.
	 *
	 * @param array $definitions
	 *
	 * @return Schema  Pour chainage
	 */
	public function addPropertiesFromDef(array $definitions): Schema
	{
		foreach ($definitions as $prop_name => $definition) {
			$this->addPropertyFromDef($prop_name, $definition);
		}
		return $this;
	}


	/*
	 * Getter for properties
	 */
	public function getProperties(): array
	{
		return $this->properties;
	}


	/**
	 * Cherche une propriété par son nom.
	 *
	 * @param string $prop_name
	 *
	 * @return SchemaProperty
	 */
	public function getProperty(string $prop_name): SchemaProperty
	{
		foreach ($this->properties as $property) {
			if ($property->getName() === $prop_name) {
				return $property;
			}
		}
		throw new \Mvc\Exception("Unknown property name: '$prop_name'.");
	}


	/**
	 * Renvoie true si la propriété existe.
	 *
	 * @param string $prop_name  Nom de propriété à tester
	 *
	 * @return bool
	 */
	public function hasProperty(string $prop_name): bool
	{
		foreach ($this->properties as $property) {
			if ($property->getName() == $prop_name) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Renvoie la liste des noms de toutes les propriétés.
	 *
	 * @return array
	 */
	public function getPropertyNames(): array
	{
		return array_map(function($prop) {
			return $prop->getName();
		}, $this->properties);
	}


	/**
	 * Crée une instance de ce schéma.
	 *
	 * @param array $data  Données du modèle
	 *
	 * @return Entiy
	 */
	public function newEntity(array $data): Entity
	{
		return new Entity($this, $data);
	}


	/**
	 * Vérifie si les données fournies collent au schéma.
	 * Envoie une exception si ce n'est pas le cas.
	 *
	 * @param array $data
	 */
	public function mustBeValidData(array $data)
	{
		foreach ($this->properties as $property) {
			$prop_name = $property->getName();
			Assert::mustKeyExist($data, $prop_name,
				"Missing entity property '$prop_name', unable to match the '{$this->name}' schema."
			);
			$property->mustBeValidValue($data[$prop_name]);
		}
	}


	/**
	 * Tente de convertir les données fournies vers le bon type.
	 *
	 * @param array &$data
	 *
	 * @return bool  Renvoie false si échec
	 */
	public function convertValues(&$data)
	{
		if (!is_array($data)) {
			return false;
		}
		$success = true;
		foreach ($this->properties as $property) {
			$prop_name = $property->getName();
			$type = $property->getType();
			if (!isset($data[$prop_name]) || !settype($data[$prop_name], $type)) {
				$success = false;
			}
		}
		return $success;
	}


	/*
	 * Getter for name
	 */
	public function getName(): string
	{
		return $this->name;
	}


	/*
	 * Getter for primaryKey
	 */
	public function getPrimaryKey()
	{
		return $this->primaryKey;
	}


	/**
	 * S'assure que le schéma possède une clé primaire.
	 */
	public function mustHavePrimaryKey()
	{
		Assert::mustBeNotNull($this->primaryKey,
			"The schema '{$this}' must have a primary key property"
		);
	}
}

