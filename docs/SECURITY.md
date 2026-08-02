# Sécurité locale

- Conserver les liaisons Docker sur `127.0.0.1`.
- Définir une clé `APP_ENCRYPTION_KEY` aléatoire de 32 octets encodée en base64.
- Ne jamais versionner `.env`, les volumes Docker, les CV ni les jetons OAuth.
- Utiliser uniquement le scope Gmail `gmail.readonly`.
- Révoquer le client OAuth Google si le Mac est perdu ou compromis.
- Vérifier chaque formulaire avant l’envoi final.
- Ne pas élargir les permissions de l’extension sans besoin concret.
