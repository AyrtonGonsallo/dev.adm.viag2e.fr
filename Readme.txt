acheteur = acquereur = mandant. C'est l'onglet acheteur qui apparait sur les biens de type vendeur.
débirentier = personne qui doit payer une rente.
crédirentier = c'est le bénéficiaire de la rente. C'est celui qui recoit l'argent.



Sur les biens vendeurs on prends toujours les données de l'acheteur/mandant/acquéreur(dernier onglet de property) pour générer la facture.
ur les biens acheteurs par défaut on prends les données de l'acheteur/mandant/acquéreur(dernier onglet de property) pour générer la facture.
Si le débirentier est l’acquéreur pas la peine de cocher le switch "débirentier different du mandant".
Mais si le switch "débirentier different du mandant" est coché on prends le debirentier(celui qui est indiqué dans le formulaire en bas de ce switch).

quand on fait le cron:invoices les erreurs concernant les fichiers sont dues au mail(lignes 662 a 668) de croninvoicescommand.php
c'est a cause du chemin de la ligne 25 de config/services.yaml
sur le prod a chaque execution du cron, on vide le tmp/pdf

pour voir les concernes
SELECT id,last_quarterly_invoice FROM `property` WHERE last_quarterly_invoice="2023-12-20" and start_date_management<"2024-01-31" and condominium_fees>0 and active=1;
pour reparer
update `property` set last_quarterly_invoice="2023-11-01" where last_quarterly_invoice="2023-12-20" and start_date_management<"2024-01-31" and condominium_fees>0 and active=1;
pour verifier 
SELECT id,last_quarterly_invoice FROM `property` WHERE last_quarterly_invoice<"2023-11-31" and start_date_management<"2024-01-31" and condominium_fees>0 and active=1;
second probleme 
SELECT id,last_invoice,start_date_management,active,billing_disabled FROM `property` WHERE last_invoice<"2023-11-30" and start_date_management<"2024-01-30" and billing_disabled=0 and active=1
SELECT id,last_invoice,start_date_management,active,billing_disabled,annuities_disabled,honoraries_disabled,valeur_indice_reference_object_id,valeur_indexation_normale FROM `property` WHERE last_invoice<"2023-11-30" and start_date_management<"2024-01-30" and billing_disabled=0 and active=1;
update property set annuities_disabled=1 where id=98;
update `property` set last_invoice="2023-11-29" where last_invoice="2024-01-04"

tester les factures
update `property` set last_invoice="2023-11-29" where id=103 or id=84 or id=99 or id=93 or id=24 or id=98;


en cas de bug sur le prod activer le end=dev
avent d'uploader generated files remplacer
/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf
/var/www/vhosts/adm.viag2e.fr/adm.viag2e.fr/var/tmp/
les boss recoivent un copie des emails remplacer
->setFrom($this->mail_from)
->setBcc($this->mail_from)
->setTo($invoice->getMailTarget())
$message1->setCc($invoice->getMailCc());