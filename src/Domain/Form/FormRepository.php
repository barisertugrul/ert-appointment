<?php declare(strict_types=1);
namespace ERTAppointment\Domain\Form;

interface FormRepository {

	public function findForScope( string $scope, ?int $scopeId ): ?Form;
	public function findById( int $id ): ?Form;
	public function save( Form $form ): Form;
	public function delete( int $id ): bool;
}
