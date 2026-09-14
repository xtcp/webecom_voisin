<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260914130722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, datetime DATETIME NOT NULL, user_id_id INT NOT NULL, post_id INT NOT NULL, INDEX IDX_9474526C9D86650F (user_id_id), INDEX IDX_9474526C4B89032C (post_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE friend_request (id INT AUTO_INCREMENT NOT NULL, status INT NOT NULL, datetime DATETIME NOT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_F284D94F624B39D (sender_id), INDEX IDX_F284D94CD53EDB6 (receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE likes (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, image VARCHAR(255) DEFAULT NULL, visibility TINYINT NOT NULL, datetime_creation DATETIME NOT NULL, datetime_modification DATETIME DEFAULT NULL, user_id_id INT NOT NULL, INDEX IDX_5A8A6C8D9D86650F (user_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_post (user_id INT NOT NULL, post_id INT NOT NULL, INDEX IDX_200B2044A76ED395 (user_id), INDEX IDX_200B20444B89032C (post_id), PRIMARY KEY (user_id, post_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C9D86650F FOREIGN KEY (user_id_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C4B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94F624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D9D86650F FOREIGN KEY (user_id_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE user_post ADD CONSTRAINT FK_200B2044A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_post ADD CONSTRAINT FK_200B20444B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C9D86650F');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C4B89032C');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94F624B39D');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94CD53EDB6');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D9D86650F');
        $this->addSql('ALTER TABLE user_post DROP FOREIGN KEY FK_200B2044A76ED395');
        $this->addSql('ALTER TABLE user_post DROP FOREIGN KEY FK_200B20444B89032C');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE friend_request');
        $this->addSql('DROP TABLE likes');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE user_post');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
