# Migrations

Chaque migration NNN a jusqu'a trois fichiers :

| Fichier | Role | Quand |
|---|---|---|
| `NNN_nom.sql` | la migration (idempotente) | a executer, **seul** |
| `NNN_nom_verif.sql` | controle en lecture seule | apres la migration, a volonte |
| `NNN_nom_retour.sql` | annulation | **uniquement pour annuler** ; supprime des colonnes ou tables |

Regles :
- Coller **un fichier a la fois** dans phpMyAdmin (onglet SQL), dans l'ordre des numeros.
- Ne jamais envoyer tous les `NNN_*` d'un coup : par ordre alphabetique, `_retour` passe avant `_verif` et defait la migration.
- Sauvegarder la base (Exporter) avant.
