<?php

namespace App\Command;

use App\Entity\Chat;
use App\Entity\Message;
use App\Entity\Participant;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:json-chat-data',
    description: 'Imports chats from json',
)]
class ImportDataFromJsonCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private string $jsonFileDir;

    public function __construct(EntityManagerInterface $entityManager, string $jsonFileDir)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->jsonFileDir = $jsonFileDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesDirs = $this->getJsonFiles();
        $loader = $io->createProgressBar(count($filesDirs));
        $loader->setMaxSteps(count($filesDirs));
        $loader->start();
        foreach ($filesDirs as $fileDirPath => $fileDir) {
            foreach ($fileDir as $file) {
                $this->createChat($fileDirPath.DIRECTORY_SEPARATOR.$file);
                $this->entityManager->clear();
            }
            $loader->advance();
        }

        $loader->finish();
        $io->success('Chats imported successfully');

        return Command::SUCCESS;
    }

    private function getJsonFiles(): array
    {
        $directories = array_diff(scandir($this->jsonFileDir), ['..', '.']);
        $files = [];
        foreach ($directories as $directory) {
            $files[$directory] = array_diff(scandir($this->jsonFileDir . '/' . $directory), ['..', '.']);
            $files[$directory] = array_filter($files[$directory], fn ($file) => str_contains($file, '.json'));
        }

        return $files;
    }

    private function createChat(string $filePath): void
    {
        $file = file_get_contents($this->jsonFileDir.DIRECTORY_SEPARATOR.$filePath);
        $data = json_decode($file, true);
        $chat = new Chat();
        $title = utf8_decode($data['title']);
        $fileName = explode('/', $filePath)[1];
        $chat->setTitle(sprintf('%s-%s', $title, $fileName));
        foreach ($data['participants'] as $participantObj) {
            $participant = new Participant();
            $participant->setName(utf8_decode($participantObj['name']));
            $chat->addParticipant($participant);
        }

        $this->addChatMessages($chat, $data['messages']);

        $this->entityManager->persist($chat);
        $this->entityManager->flush();
    }

    private function addChatMessages(Chat $chat, array $messages): void
    {
        $participants = [];
        foreach ($chat->getParticipants() as $participant) {
            $participants[$participant->getName()] = $participant;
        }
        $participantsKeys = array_keys($participants);
        foreach ($messages as $messageObj) {
            if (!empty($messageObj['content'])) {
                $message = new Message();
                $message->setChat($chat);
                if (!empty($participants[utf8_decode($messageObj['sender_name'])])) {
                    $message->setAuthor($participants[utf8_decode($messageObj['sender_name'])]);
                } else {
                    $similar = [];
                    foreach ($participantsKeys as $participant) {
                        similar_text($messageObj['sender_name'], $participant, $percent);
                        $similar[$participant] = $percent;
                    }
                    $message->setAuthor($participants[array_search(max($similar), $similar)]);
                }
                $message->setTimestamp((new DateTime())->setTimestamp($messageObj['timestamp_ms'] / 1000));
                $message->setContent(utf8_decode($messageObj['content']));
                $chat->addMessage($message);
            }
        }

        $this->entityManager->persist($chat);
        $this->entityManager->flush();
    }
}
