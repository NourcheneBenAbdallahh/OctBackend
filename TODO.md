# TODO - Mise à jour Stock + Inventaire

✅ Corriger update lot pour synchroniser stocks

**En cours - Période inventaire:**
- [x] 1. Créer migration stock_inventaires_add_periodes
- [x] 2. Modifier StockInventaire model
- [x] 3. Ajouter getLotsSumPeriode() dans StockService
- [x] 4. Modifier StockInventaireService createInventaire()
- [x] 5. Modifier GraphQL schema stockInventaire.graphql 
- [x] 5.1 Ajouter periode_debut, periode_fin dans type et UpdateInput
- [x] 6. Modifier StockInventaireMutator
- [ ] 7. php artisan migrate
- [ ] 8. Tester
- [ ] 7. php artisan migrate
- [ ] 8. Tester
