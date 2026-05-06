# Documentation de Gestion AWS pour le Projet ERP Immobilier

## Table des Matières

1. [Accès à la Console de Gestion AWS](#accès-à-la-console-de-gestion-aws)
2. [Gestion des Instances EC2](#gestion-des-instances-ec2)
3. [Gestion de la Base de Données RDS](#gestion-de-la-base-de-données-rds)
4. [Surveillance et Maintenance](#surveillance-et-maintenance)
5. [Bonnes Pratiques de Sécurité](#bonnes-pratiques-de-sécurité)
6. [Optimisation des Coûts](#optimisation-des-coûts)
7. [Résolution des Problèmes](#résolution-des-problèmes)

## Accès à la Console de Gestion AWS

### Connexion à la Console AWS

1. Accédez à [https://aws.amazon.com/console/](https://aws.amazon.com/console/)
2. Entrez vos identifiants de compte AWS
3. Après la connexion, vous verrez le tableau de bord de la console de gestion AWS

[![image.png](https://i.postimg.cc/NF70rMBq/image.png)](https://postimg.cc/0KjvT9zc)

### Navigation vers Vos Ressources

- **Instance EC2** : Services → Calcul → EC2
- **Base de Données RDS** : Services → Base de données → RDS

[![image.png](https://i.postimg.cc/q7PbNB1G/image.png)](https://postimg.cc/VSFBTwQS)
[![image.png](https://i.postimg.cc/J0nkmZjY/image.png)](https://postimg.cc/qhPRxtb2)

## Gestion des Instances EC2

### Visualisation de Votre Instance EC2

1. Naviguez vers EC2 dans la console AWS
2. Cliquez sur "Instances" dans la barre latérale gauche
3. Vous devriez voir votre instance (ec2-16-16-56-93.eu-north-1.compute.amazonaws.com)

[![image.png](https://i.postimg.cc/9M1LJPWL/image.png)](https://postimg.cc/jww6j7nf)

### Démarrage/Arrêt de Votre Instance EC2

1. Sélectionnez votre instance dans la liste
2. Cliquez sur "État de l'instance" dans le menu supérieur
3. Choisissez "Démarrer l'instance" ou "Arrêter l'instance" selon le besoin

[![image.png](https://i.postimg.cc/NM4H3YP4/image.png)](https://postimg.cc/628qRsK2)

### Connexion à Votre Instance EC2

#### Utilisation de SSH

```bash
ssh -i /chemin/vers/votre-clé.pem ec2-user@ec2-16-16-56-93.eu-north-1.compute.amazonaws.com
```

#### Utilisation d'EC2 Instance Connect (Basé sur Navigateur)

1. Sélectionnez votre instance
2. Cliquez sur "Connexion" dans le menu supérieur
3. Choisissez l'onglet "EC2 Instance Connect"
4. Cliquez sur "Connexion"

[![image.png](https://i.postimg.cc/2jHD5JsX/image.png)](https://postimg.cc/yWS5QfYF)

[![image.png](https://i.postimg.cc/mkKpHQNh/image.png)](https://postimg.cc/2LFxpLtf)

[![image.png](https://i.postimg.cc/7LvCWbX7/image.png)](https://postimg.cc/mPw2cLv2)

### Surveillance des Performances EC2

1. Sélectionnez votre instance
2. Cliquez sur l'onglet "Surveillance"
3. Visualisez les métriques de CPU, réseau et disque
4. Pour des métriques plus détaillées, cliquez sur "Gérer la surveillance détaillée"

[![image.png](https://i.postimg.cc/25BtC94M/image.png)](https://postimg.cc/7Cw9VVtn)


## Gestion de la Base de Données RDS

### Visualisation de Votre Base de Données RDS

1. Naviguez vers RDS dans la console AWS
2. Cliquez sur "Bases de données" dans la barre latérale gauche
3. Vous devriez voir votre base de données (erp-studio3d.cng8secmmw73.eu-north-1.rds.amazonaws.com)

[![image.png](https://i.postimg.cc/jS937hGp/image.png)](https://postimg.cc/yDmTq06j)

### Modification des Paramètres de la Base de Données

1. Sélectionnez votre base de données
2. Cliquez sur "Modifier" dans le menu supérieur
3. Ajustez les paramètres selon vos besoins (classe d'instance, stockage, etc.)

[![image.png](https://i.postimg.cc/Hnp6gvyy/image.png)](https://postimg.cc/sQNYPcKj)

4. Choisissez quand appliquer les modifications (immédiatement ou pendant la fenêtre de maintenance)
5. Cliquez sur "Continuer" puis sur "Modifier l'instance de base de données"

### Création de Snapshots de Base de Données

1. Sélectionnez votre base de données
2. Cliquez sur "Actions" → "Prendre un snapshot"
3. Entrez un nom de snapshot
4. Cliquez sur "Prendre un snapshot"

[![image.png](https://i.postimg.cc/sx4gNmFx/image.png)](https://postimg.cc/BPt38xnf)

### Restauration à partir d'un Snapshot

1. Naviguez vers "Snapshots" dans la barre latérale gauche
2. Sélectionnez votre snapshot
3. Cliquez sur "Actions" → "Restaurer le snapshot"
4. Configurez les paramètres de la nouvelle instance
5. Cliquez sur "Restaurer l'instance de base de données"

[![image.png](https://i.postimg.cc/tJcn5DF6/image.png)](https://postimg.cc/4Hz346L4)

[![image.png](https://i.postimg.cc/7ZnsBTQ6/image.png)](https://postimg.cc/9DzY0z25)

### Surveillance des Performances de la Base de Données

1. Sélectionnez votre base de données
2. Cliquez sur l'onglet "Surveillance"
3. Visualisez les métriques de CPU, mémoire, stockage et connexions
4. Pour une analyse plus détaillée, cliquez sur "Surveillance améliorée" ou "Performance Insights"

[![image.png](https://i.postimg.cc/VNrc2gyg/image.png)](https://postimg.cc/VS8TCjk0)

[![image.png](https://i.postimg.cc/vZp30tG5/image.png)](https://postimg.cc/0z0YQmMN)

## Surveillance et Maintenance

### Configuration des Alarmes CloudWatch

1. Naviguez vers CloudWatch dans la console AWS
2. Cliquez sur "Alarmes" → "Créer une alarme"
3. Sélectionnez la métrique à surveiller (par exemple, utilisation du CPU EC2 ou espace de stockage libre RDS)
4. Définissez le seuil et les conditions
5. Configurez les notifications (e-mail, SMS, etc.)
6. Nommez et créez l'alarme

### Visualisation des Logs

#### Logs EC2

1. Connectez-vous à votre instance EC2 via SSH
2. Naviguez vers les logs de votre application :
   ```bash
   cd /home/ec2-user/api_0.1/storage/logs
   ```

[![image.png](https://i.postimg.cc/bJ2LX6Rz/image.png)](https://postimg.cc/34Y2mF66)
[![image.png](https://i.postimg.cc/43HpnYHP/image.png)](https://postimg.cc/cKZvb4mt)

#### Logs RDS

1. Naviguez vers RDS dans la console AWS
2. Sélectionnez votre base de données
3. Cliquez sur l'onglet "Logs et événements"
4. Visualisez et téléchargez les logs disponibles

[![image.png](https://i.postimg.cc/7Z9pc7Jh/image.png)](https://postimg.cc/ZW97vWcz)
[![image.png](https://i.postimg.cc/FzxZRb4J/image.png)](https://postimg.cc/dZLdNyDq)

### Planification de la Maintenance

1. Pour EC2 : Utilisez AWS Systems Manager pour planifier les correctifs et la maintenance
2. Pour RDS : Configurez la fenêtre de maintenance dans les paramètres de la base de données

## Optimisation des Coûts

### Surveillance des Coûts

1. Naviguez vers AWS Cost Explorer
2. Visualisez la répartition des coûts par service

[![image.png](https://i.postimg.cc/prBHvWws/image.png)](https://postimg.cc/zH3MSrXh)

3. Configurez des budgets et des alertes

[![image.png](https://i.postimg.cc/vmpgghcp/image.png)](https://postimg.cc/yJyY2FhP)

[![image.png](https://i.postimg.cc/JnvfWyY3/image.png)](https://postimg.cc/hz8yLGBh)

"My Zero-Spend Budget" ici est un budget que j'ai créé pour que les services ne dépensent pas d'argent."

### Stratégies d'Économie de Coûts

1. **Auto Scaling** : Implémentez l'auto-scaling pour EC2 afin de correspondre à la demande

[![image.png](https://docs.aws.amazon.com/images/autoscaling/ec2/userguide/images/asg-basic-arch.png)]

[![image.png](https://i.postimg.cc/vT8Q9X57/image.png)](https://postimg.cc/qhYVHsbg)

[![image.png](https://i.postimg.cc/TwYjsv6Z/image.png)](https://postimg.cc/cK28Kj6m)


## Résolution des Problèmes

### Problèmes Courants EC2

1. **Impossible de se connecter à EC2** :
   - Vérifiez les règles du groupe de sécurité
   - Vérifiez que l'instance est en cours d'exécution
   - Assurez-vous que la bonne clé SSH est utilisée

2. **Utilisation élevée du CPU** :
   - Vérifiez les processus en cours d'exécution : `top`
   - Examinez les logs de l'application
   - Envisagez de mettre à l'échelle l'instance

[![image.png](https://i.postimg.cc/MKcz29b4/image.png)](https://postimg.cc/LnMcjBWB)

### Problèmes Courants RDS

1. **Délai de connexion** :
   - Vérifiez les règles du groupe de sécurité
   - Vérifiez les ACL réseau
   - Assurez-vous que la base de données est disponible

[![image.png](https://i.postimg.cc/NFjCqC3z/image.png)](https://postimg.cc/gX1H3skV)

2. **Problèmes de performance** :
   - Vérifiez les logs de requêtes lentes
   - Examinez Performance Insights
   - Envisagez de mettre à l'échelle l'instance ou d'optimiser les requêtes

[![image.png](https://i.postimg.cc/7Z9pc7Jh/image.png)](https://postimg.cc/ZW97vWcz)
[![image.png](https://i.postimg.cc/FzxZRb4J/image.png)](https://postimg.cc/dZLdNyDq)

3. **Stockage plein** :
   - Augmentez le stockage alloué
   - Nettoyez les données inutiles

[![image.png](https://i.postimg.cc/tgWyc7bM/image.png)](https://postimg.cc/bGyKDysx)
[![image.png](https://i.postimg.cc/QMGZcs7k/image.png)](https://postimg.cc/BjgzGrMX)

## Ressources Supplémentaires

- [Documentation AWS EC2](https://docs.aws.amazon.com/fr_fr/ec2/)
- [Documentation AWS RDS](https://docs.aws.amazon.com/fr_fr/rds/)
- [Documentation AWS CloudWatch](https://docs.aws.amazon.com/fr_fr/cloudwatch/)
- [Documentation AWS Cost Management](https://docs.aws.amazon.com/fr_fr/cost-management/)

---
