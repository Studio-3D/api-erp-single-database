<html>
<head>
    <title>Réinitialisation du mot de passe</title>
        <style>
        .button {
            display: inline-block;
            background-color: #000000;
            color: rgb(229, 229, 229);
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h2>Réinitialisation du mot de passe</h2>
    <h3>Bonjour!</h3>
    <h4>Pour réinitialiser votre mot de passe, veuillez cliquer sur le lien suivant :</h4>
    
    <h4>
        <a class="button" href="{{$resetUrl}}">Réinitialisation du mot de passe</a>
    </h4>
    <H1>{{$confirmationCode}} </H1>
   
    <h4>Ce lien de réinitialisation de mot de h4asse exh4irera dans quelques minutes.</h4>
    <h4>Si vous avez des questions ou avez besoin d'aide supplémentaire, n'hésitez pas à nous contacter.</p>
    <h3>Merci!</h3>
</body>
</html>
