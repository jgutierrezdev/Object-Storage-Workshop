# gallery/urls.py
from django.urls import path
from . import views

urlpatterns = [
    path('', views.index, name='index'),
    # La ruta para eliminar productos se agregará en la Fase 6.
]