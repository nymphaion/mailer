# Mailer

![Version](https://poser.pugx.org/nymphaion/mailer/v/stable.svg)
![Downloads](https://poser.pugx.org/nymphaion/mailer/d/total.svg)
![License](https://poser.pugx.org/nymphaion/mailer/license.svg)

Simple SMTP mailer

### Example

```php
// If you're testing this script you have to set SERVER_NAME
putenv("SERVER_NAME=website.com");
 
(new Nymphaion\Mail\Smtp('smtp.mail.com', 'sabrina@mail.com', 'super_password'))
    ->sender('Sabrina')
    ->to('mykola@gmail.com')
    ->html('<p>Hello man!</p>')
    ->send();
}
```
