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
les boss recoivent une copie des emails remplacer
->setFrom($this->mail_from)
->setBcc($this->mail_from)
->setTo($invoice->getMailTarget())
$message1->setCc($invoice->getMailCc());


correction bug quittances

SELECT p.id as property_id,p.last_receipt,i.id as invoice_id, i.status,i.data FROM `invoice` i,property p WHERE i.status=5 and p.id=i.property_id order by p.last_receipt desc;


8216,7934,8328,8462,8405
update invoice set status=4 where id in (8216,7934,8328,8462,8405)

pour les quittances
generate file 1 honoraires
generate file 2 rente
pour les avis 
generate file 1 rente
generate file 2 honoraires

tests 
SELECT p.id,p.revaluation_date,r.date,r.id as rev_index FROM `property` p,revaluation_history r WHERE p.valeur_indice_reference_object_id=r.id and r.date<"2023-01-01"
ORDER BY p.`revaluation_date` DESC;

bien 
SELECT * FROM `invoice` WHERE date >="2023-12-20 00:00:01" and date <="2024-01-08 23:59:01" and category in (0,1) ORDER BY `date` DESC;
update property set bank_ics="FR12ZZZ886B32" where 1;

regarder le pb ics si elle rajoute un bien

SELECT * FROM `invoice` WHERE date >="2024-01-08 00:00:01" and date <="2024-01-31 23:59:01" and category in (0) ORDER BY `date` DESC;

comment fonctionne l'export bank ?
dans invoice repository listByDate2 autrefois listByDateNE
SELECT i.id, i.date, i.data, w.bank_ics, p.id AS property_id, p.title, p.active FROM `invoice` i JOIN `property` p ON i.property_id = p.id JOIN `warrant` w ON w.id = p.warrant_id WHERE i.date >= '2024-06-01' AND i.date <= '2024-06-30' AND i.type = 1;
SELECT i.id,i.date,i.data FROM `invoice` i WHERE i.date>="2024-06-01" and i.date<="2024-06-30" and i.type=1;

update `property` set last_invoice="2023-12-20" WHERE last_invoice="2024-01-08" or last_invoice="2024-01-09";


4645
4644
4643
4642

SELECT id,valeur_indexation_normale,valeur_indice_reference_object_id FROM `property` where id in (39,57,59,60);

UPDATE `property` SET `last_invoice` = '2023-12-20' WHERE `property`.`id` = 20;
UPDATE `invoice` SET `status` = '4' WHERE `invoice`.`id` = 8242;
accents aout
SELECT * FROM `invoice` where data like "%\"month\":\"f%" and type=1 and category=0 ORDER BY `invoice`.`date` DESC
aout  7650
fevrier 9128
decemnbre 8332

UPDATE `invoice` SET `status` = '4' WHERE `invoice`.`id` in (7650,9128,8332);
celui ci 9181 est problematique pour aout avec son \u00c3\u0083\u00c2\u00bb, mais on verra ca plus tard 


generée: L'avis d'echéance est payé et le cron a tourné et généré la quittance, l'avis lui est marqué traité
 la quittance est validée dans le back office au niveau de la liste des factures
 le cron a tourné et a envoyé la quittance payée, la quittance est marquée envoyée
Donc la question c'est "une quittance n'a que 2 status ? généré et envoyé ?"









seules les factures du 09 et du 08 janvier sont concernées a cause des suppresssions effectuées pendant cette période pour les destinataires qui n'étaient pas corrects
on avait fait plusieurs series de generations
premiere par exemple 4454 a 4554 incorrecte
2eme par exemple 4555 a 4655
3eme 4656 a 4756
4eme 4757 a 4857
5eme 4857 a 4881
derniere et bonne 4802 a 5001 on garde celle la comme elle a ete envoyée et on supprime les autres
lors de la prochaine execution il repart a 4559
solution regenerer celles de janvier avec des numeros entre 4455 et 4558

depuis le 09 janvier facture 5001
4326 le 20 novembre
20 03 2024  4765 a 4867 86 avis rentes et honoraires + 16 frais de copros
23 02 2024  4754 a 4764
22 02 2024  4753
20 02 2024  4659 a 4752
22 01 2024  4559 a 4645
9 01 2024   4902 a 5001 
8 01 2024   4882 a 4901 
22 12 2023  4454(4334)
29 11 2023  4334
4332




    SELECT * FROM `invoice` where date >= "2024-01-08" and date <"2024-01-10" order by number +1;

    toutes les quittances copros
    SELECT * FROM `invoice` where number >= 4882 and number <=5001 and type=2 and category=1 ORDER BY `invoice`.`number` DESC;
    tous les avis copros
    SELECT * FROM `invoice` where number >= 4882 and number <=5001 and type=1 and category=1 ORDER BY `invoice`.`number` DESC;
    toutes les quittances rentes
    SELECT * FROM `invoice` where number >= 4882 and number <=5001 and type=2 and category=0 ORDER BY `invoice`.`number` DESC;
    tous les avis rentes
    SELECT * FROM `invoice` where number >= 4882 and number <=5001 and type=1 and category=0 ORDER BY `invoice`.`number` DESC;
     toutes les avis (190)
    SELECT * FROM `invoice` where number >= 4882 and number <=5001 and type=1 ORDER BY `invoice`.`number` ASC;
    toutes les quittances (187) avoirs
    SELECT * FROM `invoice` where number >= 4882 and number <=5001 and type=2 ORDER BY `invoice`.`number` ASC;
    avoirs 5002 a 5189
    5190 a 5380








- Créer un avoir pour chacune des factures  QUITTANCES de janvier : Les avis d’échéances non quittancés ne sont pas à émettre en avoir. 
Numérotation : à la suite de la dernière facture du dernier avis d’échéance de janvier : en effet, à partir de la 5002




de 5002 a 5104 avoirs sur les quittances payées 103 (16 copros + 84 honoraires+rentes +3 rentes) 187 fichiers  les honoraires de (4933,4934,4965) ne sont pas payés

81 mails/ 187 cas SELECT * FROM `invoice` WHERE number <=5001 and number >=4882 and type=2; 187
16 copros  SELECT * FROM `invoice` WHERE number <=5001 and number >=4882 and type=2 and category=1;
84 honoraires SELECT * FROM `invoice` WHERE number <=5001 and number >=4882 and type=2 and category=0 and file_id is null;
87 rentes SELECT * FROM `invoice` WHERE number <=5001 and number >=4882 and type=2 and category=0 and file2_id is null;

- Re-générer TOUS les avis d'échéances de janvier :

de 5105 a 5207 102 (16 copros + 87 honoraires+rentes) 190 fichiers
 190 cas SELECT * FROM `invoice` WHERE number <=5001 and number >=4882 and type=1; 190

- Re-générer les quittances de janvier uniquement celles dont le paiement à bien été validé :

de 5105 a 5207  102 (16 copros + 84 honoraires+rentes +3 rentes) 187 fichiers

 187 cas  SELECT * FROM `invoice` WHERE number <=5001 and number >=4882 and type=2;


janvier
 174 avis 87 biens
 16 copros
 quittances
 171 quittances ( 87 AVEC rentes seul et 84 avec honn seuls) 84 bien ont les 2 et 3 n'ont que la rente (4933,4934,4965) donc supprimer (5156,5157,5188)
 16 quittances copro
 102= 86+16

 UPDATE invoice set status=4 where number<=5207 and number>=5105;
  UPDATE invoice set status=2 where number in (5156,5157,5188) and file_id is null


SELECT id,active,billing_disabled,start_date_management,valeur_indexation_normale, last_invoice FROM `property` WHERE active=1 and billing_disabled=0 and start_date_management<"2024-05-31" and last_invoice<"2024-03-31" order by id desc;

SELECT f.id,f.number,p.title FROM `facture_mensuelle` f,property p WHERE f.property_id=p.Id and p.warrant_id=16 and f.date>"2024-04-19" order by f.number desc;
ajouter facture_id dans invoice et invoice_id dans facture


Pour faire simple:
Sur un mandat vendeur:
Le débirentier est l’acheteur(buyer)
Le débirentier est le mandant (si tout en bas de l'onglet bien, la case "débirentier différent du mandant" est cochée c'est cette partie qu’il faut prendre )
Le crédirentier est au niveau des premiers champs de l'onglet bien
exemple: https://adm.viag2e.fr/property/view/22?onglet=m_tabs_pay

Sur les mandats acquéreurs:
Le débirentier est le mandant (si tout en bas de l'onglet bien, la case "débirentier différent du mandant" est cochée c'est cette partie qu’il faut prendre )
Le crédirentier est au niveau des premiers champs de l'onglet bien
exemple: https://adm.viag2e.fr/property/view/44


update `property` set date_maj_indice_ref="2024-03-04" where revaluation_date like "%1-7%";
SELECT p.id,p.title,w.firstname,w.lastname,p.firstname1,p.lastname1,p.firstname2,p.lastname2 FROM `property` p,warrant w WHERE p.warrant_id=w.id and (w.firstname=p.firstname1 or w.firstname=p.firstname2)

SELECT * FROM `invoice` WHERE property_id=16 and date<="2023-12-31" and date>="2023-01-01" and category=1 and type=1 and status=5;
SELECT * FROM `property` where active=1 and revaluation_date like "%1-8%";
25,26,35,64,80,81,105,116
concernes
64,105,


update `property` set date_maj_indice_ref="2024-03-04" where id=81 and revaluation_date like "%1-8%";

31,43,63,82,103,119,124,126
63,103,119,124,126


mettre en prod les lignes 1026, 352 et 373 de croninvoicescommand


factures a annuler 
herbet 122
5681,5790 generer avoir et renvoyer avec nouveau numero 
duchemin 124
5791,5682 generer avoir et renvoyer avec nouveau numero 
manuelles non payées
5591,5592,5588,5589,5590,5581,5582,5584,5585,5586,5587
accents bien duchemin
UPDATE `property` SET `address` = '2 allée Guillaume Apollinaire Résidence Les Coteaux de Montchapet' WHERE `property`.`id` = 124;
UPDATE `property` SET `good_address` = '2 allée Guillaume Apollinaire Résidence Les Coteaux de Montchapet 21000 DIJON' WHERE `property`.`id` = 124;

voir les copro
SELECT * FROM `property` WHERE last_quarterly_invoice < "2024-11-30" and condominium_fees > 0 and start_date_management <"2025-01-30" and active = true;

SELECT * FROM `facture_mensuelle` where date>"2024-11-10";
SELECT * FROM `facture_mensuelle` where date>"2024-10-10" and date<"2024-11-10";
corriger la copro
SELECT * FROM `invoice` WHERE number=5696;

SELECT id,title,revaluation_date,date_maj_indice_ref,warrant_id FROM `property` where warrant_id=16 and revaluation_date like "%1-1";

94,95,96,97,98,99,100,101,106,107,109,110,111

SELECT id,title,date_maj_indice_ref FROM `property` where id in(125,132,134,133,122) ORDER BY `id` DESC;