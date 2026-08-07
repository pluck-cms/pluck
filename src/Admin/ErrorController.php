<?php
declare(strict_types=1);

namespace Pluck\Admin;

final class ErrorController extends Controller
{
	public function notFound(): never
	{
		http_response_code(404);
		$this->render('errors/admin', [
			'title' => $this->t('error.title.no_such_screen'),
			'status' => 404,
			'message' => 'That address does not match anything in the admin.',
		]);
	}
}
