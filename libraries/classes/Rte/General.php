<?php
/**
 * General functions.
 */
declare(strict_types=1);

namespace PhpMyAdmin\Rte;

use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Message;
use PhpMyAdmin\Response;
use PhpMyAdmin\Util;
use function htmlspecialchars;
use function sprintf;

/**
 * PhpMyAdmin\Rte\General class
 */
class General
{
    /** @var DatabaseInterface */
    private $dbi;

    /**
     * @param DatabaseInterface $dbi DatabaseInterface object
     */
    public function __construct(DatabaseInterface $dbi)
    {
        $this->dbi = $dbi;
    }

    /**
     * Check result
     *
     * @param resource|bool $result          Query result
     * @param string        $error           Error to add
     * @param string        $createStatement Query
     * @param array         $errors          Errors
     *
     * @return array
     */
    public function checkResult($result, $error, $createStatement, array $errors)
    {
        if ($result) {
            return $errors;
        }

        // OMG, this is really bad! We dropped the query,
        // failed to create a new one
        // and now even the backup query does not execute!
        // This should not happen, but we better handle
        // this just in case.
        $errors[] = $error . '<br>'
            . __('The backed up query was:')
            . '"' . htmlspecialchars($createStatement) . '"<br>'
            . __('MySQL said: ') . $this->dbi->getError();

        return $errors;
    }

    /**
     * Send TRI or EVN editor via ajax or by echoing.
     *
     * @param string $type      TRI or EVN
     * @param string $mode      Editor mode 'add' or 'edit'
     * @param array  $item      Data necessary to create the editor
     * @param string $title     Title of the editor
     * @param string $db        Database
     * @param string $operation Operation 'change' or ''
     *
     * @return void
     */
    public function sendEditor($type, $mode, array $item, $title, $db, $operation = null)
    {
        $events = new Events($this->dbi);
        $triggers = new Triggers($this->dbi);
        $response = Response::getInstance();
        if ($item !== false) {
            // Show form
            if ($type == 'TRI') {
                $editor = $triggers->getEditorForm($mode, $item);
            } else { // EVN
                $editor = $events->getEditorForm($mode, $operation, $item);
            }
            if ($response->isAjax()) {
                $response->addJSON('message', $editor);
                $response->addJSON('title', $title);
            } else {
                echo "\n\n<h2>" . $title . "</h2>\n\n" . $editor;
                unset($_POST);
            }
            exit;
        } else {
            if ($type == 'TRI') {
                $notFound = __('No trigger with name %1$s found in database %2$s.');
            } else { // EVN
                $notFound = __('No event with name %1$s found in database %2$s.');
            }

            $message  = __('Error in processing request:') . ' ';
            $message .= sprintf(
                $notFound,
                htmlspecialchars(Util::backquote($_REQUEST['item_name'])),
                htmlspecialchars(Util::backquote($db))
            );
            $message = Message::error($message);
            if ($response->isAjax()) {
                $response->setRequestStatus(false);
                $response->addJSON('message', $message);
                exit;
            } else {
                $message->display();
            }
        }
    }
}
