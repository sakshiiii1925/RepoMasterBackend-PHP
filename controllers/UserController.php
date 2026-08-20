<?php
require_once __DIR__ . '/../helpers/response.php';
class UserController {
 public function __construct(private UserService $s){}
 public function registerAdmin(){jsonResponse($this->s->registerAdmin(requestBody()));}
 public function registerUser(){jsonResponse($this->s->registerUser(requestBody()));}
 public function login(){jsonResponse($this->s->login(requestBody()));}
 public function pending(){jsonResponse($this->s->getPendingUsers((string)queryParam('agencyId','')));}
 public function approve($id){jsonResponse($this->s->approveUser((int)$id));}
 public function reject($id){jsonResponse($this->s->rejectUser((int)$id));}
 public function profile(){jsonResponse($this->s->getProfile((string)queryParam('email','')));}
 public function update($id){jsonResponse($this->s->updateUser((int)$id,requestBody()));}
 public function forgot(){jsonResponse($this->s->forgotPassword((string)queryParam('email','')));}
 public function reset(){jsonResponse($this->s->resetPassword((string)queryParam('email',''),(string)queryParam('newPassword','')));}
 public function verify(){jsonResponse(['exists'=>$this->s->verifyEmail((string)queryParam('email',''))]);}
 public function users(){jsonResponse($this->s->getUsersByAdmin((string)queryParam('agencyId','')));}
 public function search(){jsonResponse($this->s->searchUsers((string)queryParam('agencyId',''),(string)queryParam('search','')));}
 public function delete($id){$this->s->deleteUser((int)$id);jsonResponse('User deleted successfully');}
 public function approved(){jsonResponse($this->s->getApprovedUsersByAgency((string)queryParam('agencyId','')));}
 public function pendingCount(){jsonResponse(['count'=>$this->s->getPendingUserCount()]);}
}
