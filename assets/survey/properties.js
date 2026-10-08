/* Shared Creator serialization. Scores are independently calculated on the server. */
for(const p of [
 {name:'responseOnly:boolean',displayName:'Réponse liée à un barème partagé',default:false},
 {name:'assessmentOnly:boolean',displayName:'Critère réservé au correcteur',default:false},
 {name:'answerFields:text',displayName:'Réponses liées (noms JSON)'},
 {name:'maxScore:number',displayName:'Note maximale',default:0,minValue:0},
 {name:'competency:text',displayName:'Code compétence'},
 {name:'questionCode:text',displayName:'Code question'},
 {name:'teacherExplanation:text',displayName:'Correction réservée au formateur'},
 {name:'sourceRubric:text',displayName:'Barème du document source'},
 {name:'modelAnswer:text',displayName:'Réponse modèle'},
 {name:'gradingKeywords:text',displayName:'Mots clés séparés par des virgules'},
 {name:'correctOrder:text',displayName:'Ordre attendu (tableau JSON)'},
 {name:'matchingAnswer:text',displayName:'Correspondances attendues (objet JSON)'},
 {name:'partialCredit:boolean',displayName:'Crédit partiel',default:false},
 {name:'unscored:boolean',displayName:'Critère qualitatif sans note chiffrée',default:false},
 {name:'scoringMode',displayName:'Méthode de correction',default:'none',choices:['none','manual','choice','sumChoices','exactMatch','rating','ranking','matching','keywords']}
])Survey.Serializer.addProperty('question',{...p,category:'Notation'});
Survey.Serializer.addProperty('itemvalue',{name:'score:number',displayName:'Points attribués',default:0});
