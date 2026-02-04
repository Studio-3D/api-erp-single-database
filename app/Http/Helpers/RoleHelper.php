<?php

namespace App\Http\Helpers;

use Illuminate\Support\Facades\Auth;

class RoleHelper
{

    public static function SuperAdmin()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 1)) {
            return true;
        }

        return false;
    }
    public static function Admin()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 2)) {
            return true;
        }

        return false;
    }
    public static function AdminSup()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 1 || Auth::guard('api')->user()->role == 2)) {
            return true;
        }

        return false;
    }
     public static function AdminSup_RC()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 1 || Auth::guard('api')->user()->role == 2|| Auth::guard('api')->user()->role == 9)) {
            return true;
        }

        return false;
    }
    public static function ACSup()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 1 || Auth::guard('api')->user()->role == 2 || Auth::guard('api')->user()->role == 3)) {
            return true;
        }
        return false;
    }
 public static function ACSup_RC()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 1 || Auth::guard('api')->user()->role == 2 || Auth::guard('api')->user()->role == 3||Auth::guard('api')->user()->role == 9)) {
            return true;
        }
        return false;
    }

    public static function AC()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 2 ||  Auth::guard('api')->user()->role == 3)) {
            return true;
        }
        return false;
    }

    public static function Com()
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->role == 3) {
            return true;
        }
        return false;
    }
    public static function Comptable(){

        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 7)) {

            return true;
        }
        return false;
    }

    public static function AdminComptable()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 7 || Auth::guard('api')->user()->role == 2 )) {
            return true;
        }

        return false;
    }

    public static function AdminComptableSup()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 7 || Auth::guard('api')->user()->role == 1 || Auth::guard('api')->user()->role == 2)) {
            return true;
        }

        return false;
    }
    public static function Notaire(){

        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 5)) {

            return true;
        }
        return false;
    }
     public static function RespoLivraison(){

        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 6)) {

            return true;
        }
        return false;
    }
    public static function SAV(){

        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 8)) {

            return true;
        }
        return false;
    }
  public static function RespoCommercial(){

        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role ==9)) {

            return true;
        }
        return false;
    }
     public static function AgentAdmin(){

        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role ==10)) {

            return true;
        }
        return false;
    }

    public static function Notaire_Respo_Comptable_SAV_Comm_RC_AA(){

        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 8
        || Auth::guard('api')->user()->role == 7|| Auth::guard('api')->user()->role == 6||
         Auth::guard('api')->user()->role == 5|| Auth::guard('api')->user()->role == 3||Auth::guard('api')->user()->role == 9||Auth::guard('api')->user()->role == 10)) {

            return true;
        }
        return false;
    }
     public static function AdminSavSup()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 2 || Auth::guard('api')->user()->role == 8|| Auth::guard('api')->user()->role == 1)) {
            return true;
        }
        return false;
    }
    public static function NotaireRespoL()
        {
            if (Auth::guard('api')->check() && (Auth::guard('api')->user()->role == 5 ||  Auth::guard('api')->user()->role == 6)) {
                return true;
            }
            return false;
        }

}
