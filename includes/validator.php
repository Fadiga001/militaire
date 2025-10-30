<?php

/**
 * Classe de validation centralisée
 * Gère toutes les validations de données
 */

class ValidationException extends Exception {}

class Validator
{
    private array $errors = [];
    private array $data = [];

    /**
     * Créer une instance de validation
     */
    public static function make(array $data): self
    {
        $instance = new self();
        $instance->data = $data;
        return $instance;
    }

    /**
     * Valider champ requis
     */
    public function required(string $field, string $message = null): self
    {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field][] = $message ?? "Le champ $field est requis.";
        }
        return $this;
    }

    /**
     * Valider email
     */
    public function email(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message ?? "L'email est invalide.";
        }
        return $this;
    }

    /**
     * Valider longueur minimum
     */
    public function min(string $field, int $length, string $message = null): self
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field][] = $message ?? "Le champ doit contenir au moins $length caractères.";
        }
        return $this;
    }

    /**
     * Valider longueur maximum
     */
    public function max(string $field, int $length, string $message = null): self
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field][] = $message ?? "Le champ ne doit pas dépasser $length caractères.";
        }
        return $this;
    }

    /**
     * Valider format date
     */
    public function date(string $field, string $format = 'Y-m-d', string $message = null): self
    {
        if (isset($this->data[$field])) {
            $d = DateTime::createFromFormat($format, $this->data[$field]);
            if (!$d || $d->format($format) !== $this->data[$field]) {
                $this->errors[$field][] = $message ?? "Format de date invalide.";
            }
        }
        return $this;
    }

    /**
     * Valider numérique
     */
    public function numeric(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = $message ?? "Le champ doit être numérique.";
        }
        return $this;
    }

    /**
     * Valider entier
     */
    public function integer(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
            $this->errors[$field][] = $message ?? "Le champ doit être un entier.";
        }
        return $this;
    }

    /**
     * Valider valeur dans liste
     */
    public function in(string $field, array $values, string $message = null): self
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $this->errors[$field][] = $message ?? "Valeur invalide.";
        }
        return $this;
    }

    /**
     * Valider unicité dans la BDD
     */
    public function unique(string $field, string $table, string $column, ?int $excludeId = null, string $message = null): self
    {
        if (isset($this->data[$field])) {
            global $pdo;
            $sql = "SELECT COUNT(*) as nb FROM $table WHERE $column = ?";
            $params = [$this->data[$field]];

            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ((int)$stmt->fetch()['nb'] > 0) {
                $this->errors[$field][] = $message ?? "Cette valeur existe déjà.";
            }
        }
        return $this;
    }

    /**
     * Valider match entre deux champs
     */
    public function match(string $field, string $otherField, string $message = null): self
    {
        if (isset($this->data[$field], $this->data[$otherField])) {
            if ($this->data[$field] !== $this->data[$otherField]) {
                $this->errors[$field][] = $message ?? "Les champs ne correspondent pas.";
            }
        }
        return $this;
    }

    /**
     * Vérifier si validation a échoué
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Récupérer les erreurs
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Récupérer première erreur d'un champ
     */
    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Lancer exception si validation échoue
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException(json_encode($this->errors));
        }
        return $this->data;
    }
}

// Utilisation:
/*
try {
    $validated = Validator::make($_POST)
        ->required('nom')
        ->required('email')
        ->email('email')
        ->required('password')
        ->min('password', 8)
        ->match('password', 'password_confirm', "Les mots de passe ne correspondent pas")
        ->unique('email', 'users', 'email', null, "Cet email est déjà utilisé")
        ->validate();
    
    // $validated contient les données validées
    
} catch (ValidationException $e) {
    $errors = json_decode($e->getMessage(), true);
    // Afficher les erreurs
}
*/